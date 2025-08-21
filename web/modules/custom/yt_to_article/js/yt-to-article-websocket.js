/**
 * @file
 * WebSocket client for YT to Article module.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';
  
  console.log('[YtToArticle] JavaScript file loaded');
  
  /**
   * Custom AJAX command handler for WebSocket connection.
   */
  Drupal.AjaxCommands.prototype.ytToArticleWebSocketConnect = function(ajax, response, status) {
    console.log('[YtToArticle] WebSocket connect command received', response);
    
    if (response.config) {
      // Update drupalSettings
      if (!window.drupalSettings) {
        window.drupalSettings = {};
      }
      window.drupalSettings.ytToArticle = response.config;
      
      // Create a new WebSocket instance for this request
      const wsInstance = window.createYtToArticleWebSocket();
      const requestId = response.config.requestId;
      
      // Store the instance
      window.YtToArticleWebSocketInstances.set(requestId, wsInstance);
      
      // Give DOM a moment to settle after AJAX update, then connect
      setTimeout(function() {
        console.log('[YtToArticle] Connecting to WebSocket with config:', response.config);
        wsInstance.connect(requestId);
      }, 100);
    } else {
      console.error('[YtToArticle] WebSocket connect failed - missing config');
    }
  };

  /**
   * Store for multiple WebSocket instances
   */
  window.YtToArticleWebSocketInstances = window.YtToArticleWebSocketInstances || new Map();
  
  /**
   * Factory function to create WebSocket client instances
   */
  window.createYtToArticleWebSocket = function() {
    return {
      ws: null,
      requestId: null,
      reconnectAttempts: 0,
      maxReconnectAttempts: 5,
      reconnectDelay: 1000,
    
    /**
     * Connect to WebSocket server.
     */
    connect: function(requestId) {
      console.log('[YtToArticle] Connect called with requestId:', requestId);
      console.log('[YtToArticle] drupalSettings:', drupalSettings);
      
      this.requestId = requestId;
      this.reconnectAttempts = 0;
      
      if (this.ws && this.ws.readyState === WebSocket.OPEN) {
        console.log('[YtToArticle] Closing existing WebSocket connection');
        this.ws.close();
      }
      
      if (!drupalSettings.ytToArticle || !drupalSettings.ytToArticle.wsUrl) {
        console.error('[YtToArticle] Missing WebSocket URL in drupalSettings');
        this.showError('WebSocket configuration missing');
        return;
      }
      
      const token = drupalSettings.ytToArticle.token;
      if (!token) {
        console.error('[YtToArticle] Missing API token in drupalSettings');
        this.showError('API token not configured');
        return;
      }
      
      const wsUrl = drupalSettings.ytToArticle.wsUrl + '/article/' + requestId + '?token=' + encodeURIComponent(token);
      console.log('[YtToArticle] Attempting to connect to WebSocket:', wsUrl.replace(token, '***hidden***'));
      
      try {
        this.ws = new WebSocket(wsUrl);
        this.setupEventHandlers();
        console.log('[YtToArticle] WebSocket object created successfully');
      } catch (error) {
        console.error('[YtToArticle] Failed to create WebSocket:', error);
        this.showError('Failed to establish connection');
      }
    },
    
    /**
     * Set up WebSocket event handlers.
     */
    setupEventHandlers: function() {
      this.ws.onopen = this.onOpen.bind(this);
      this.ws.onmessage = this.onMessage.bind(this);
      this.ws.onerror = this.onError.bind(this);
      this.ws.onclose = this.onClose.bind(this);
    },
    
    /**
     * Handle WebSocket connection opened.
     */
    onOpen: function(event) {
      console.log('[YtToArticle] WebSocket connected successfully');
      console.log('[YtToArticle] Connection event:', event);
      this.reconnectAttempts = 0;
      this.updateStatus('Connected', 'connected');
      
      // Clear previous messages when new connection opens
      const messagesWrapper = document.querySelector('#yt-to-article-messages-container .messages__wrapper') || 
                            document.querySelector('.yt-to-article-messages .messages__wrapper');
      if (messagesWrapper) {
        console.log('[YtToArticle] Clearing previous messages');
        messagesWrapper.innerHTML = '';
      }
      
      // Clear previous action buttons (View Article link)
      const actionsDiv = document.querySelector('.yt-to-article-actions');
      if (actionsDiv) {
        console.log('[YtToArticle] Removing previous action buttons');
        actionsDiv.remove();
      }
      
      // Display initial connection message
      this.displayMessage('Connected to article generation service', 'connected');
      
      // Disable the submit button
      this.toggleSubmitButton(false);
    },
    
    /**
     * Handle incoming WebSocket message.
     */
    onMessage: function(event) {
      try {
        const message = JSON.parse(event.data);
        console.log('[YtToArticle] WebSocket message received:', message);
        
        // Extract the actual data from the nested structure
        const data = message.data || message;
        
        // Display the message using Drupal's message system
        if (data.message) {
          console.log('[YtToArticle] Message has text, calling displayMessage');
          this.displayMessage(data.message, data.stage || 'status');
        } else {
          console.log('[YtToArticle] No message text in data');
        }
        
        // Update progress, ensuring 100% for finished state
        if (data.stage === 'finished' || data.stage === 'completed') {
          // Force 100% progress for finished state
          data.progress = 1;
        }
        
        this.updateProgress(data);
        
        if (data.stage === 'finished' || data.stage === 'completed') {
          this.onComplete(data);
        } else if (data.stage === 'error' || data.stage === 'failed') {
          this.onError(data);
        }
      } catch (error) {
        console.error('[YtToArticle] Failed to parse WebSocket message:', error);
      }
    },
    
    /**
     * Handle WebSocket error.
     */
    onError: function(error) {
      console.error('[YtToArticle] WebSocket error:', error);
      console.error('[YtToArticle] Error type:', error.type);
      console.error('[YtToArticle] ReadyState:', this.ws ? this.ws.readyState : 'No WebSocket');
      
      if (error.error || error.message) {
        this.showError(error.error || error.message);
      } else {
        this.showError('Connection error occurred');
      }
    },
    
    /**
     * Handle WebSocket connection closed.
     */
    onClose: function(event) {
      console.log('WebSocket closed:', event);
      
      if (!event.wasClean && this.reconnectAttempts < this.maxReconnectAttempts) {
        this.reconnect();
      } else if (event.wasClean) {
        this.updateStatus('Connection closed', 'closed');
      } else {
        this.showError('Connection lost');
      }
      
      // Re-enable submit button if connection closes unexpectedly
      if (!event.wasClean) {
        this.toggleSubmitButton(true);
      }
    },
    
    /**
     * Attempt to reconnect to WebSocket.
     */
    reconnect: function() {
      this.reconnectAttempts++;
      const delay = this.reconnectDelay * this.reconnectAttempts;
      
      this.updateStatus('Reconnecting... (' + this.reconnectAttempts + '/' + this.maxReconnectAttempts + ')', 'reconnecting');
      
      setTimeout(() => {
        console.log('Attempting to reconnect...');
        this.connect(this.requestId);
      }, delay);
    },
    
    /**
     * Update progress display.
     */
    updateProgress: function(data) {
      const progressBar = document.querySelector('.yt-to-article-progress-bar');
      const progressText = document.querySelector('.yt-to-article-progress-text');
      
      if (progressBar && data.progress !== undefined) {
        // Convert decimal to percentage (0.3 -> 30)
        const progressPercent = Math.round(data.progress * 100);
        progressBar.style.width = progressPercent + '%';
        progressBar.setAttribute('aria-valuenow', progressPercent);
      }
      
      if (progressText && data.progress !== undefined) {
        // Convert decimal to percentage (0.3 -> 30%)
        const progressPercent = Math.round(data.progress * 100);
        progressText.textContent = progressPercent + '%';
      }
    },
    
    /**
     * Display a message using Drupal's message system.
     */
    displayMessage: function(message, stage) {
      console.log('[YtToArticle] displayMessage called:', { message, stage });
      
      // Look for messages container in multiple locations
      let messagesWrapper = document.querySelector('#yt-to-article-messages-container .messages__wrapper') || 
                           document.querySelector('.yt-to-article-messages .messages__wrapper');
      
      // If not found, try to find the parent container and create wrapper
      if (!messagesWrapper) {
        console.warn('[YtToArticle] Messages wrapper not found, looking for container');
        const messagesContainer = document.querySelector('#yt-to-article-messages-container .yt-to-article-messages') || 
                                 document.querySelector('.yt-to-article-messages');
        
        if (messagesContainer) {
          console.log('[YtToArticle] Found messages container, looking for or creating wrapper');
          messagesWrapper = messagesContainer.querySelector('.messages__wrapper');
          if (!messagesWrapper) {
            messagesWrapper = document.createElement('div');
            messagesWrapper.className = 'messages__wrapper';
            messagesContainer.appendChild(messagesWrapper);
            console.log('[YtToArticle] Created new messages wrapper');
          }
        } else {
          console.error('[YtToArticle] No messages container found - this should not happen');
          return;
        }
      }
      
      console.log('[YtToArticle] Messages wrapper found/created, adding message');
      
      // Determine message type based on stage
      let messageType = 'status';
      if (stage === 'error' || stage === 'failed') {
        messageType = 'error';
      } else if (stage === 'warning') {
        messageType = 'warning';
      }
      
      // Create custom message element
      const messageElement = document.createElement('div');
      messageElement.className = `yt-to-article-message yt-to-article-message--${messageType}`;
      messageElement.setAttribute('data-message-stage', stage);
      
      // Add timestamp
      const timestamp = new Date().toLocaleTimeString();
      const timeElement = document.createElement('span');
      timeElement.className = 'yt-to-article-message-time';
      timeElement.textContent = timestamp;
      
      // Add message text
      const textElement = document.createElement('span');
      textElement.className = 'yt-to-article-message-text';
      textElement.textContent = message;
      
      // Compose message
      messageElement.appendChild(timeElement);
      messageElement.appendChild(textElement);
      
      // Append to messages container
      messagesWrapper.appendChild(messageElement);
      console.log('[YtToArticle] Message element appended to wrapper');
      
      // Auto-scroll to latest message
      const messagesContainer = messagesWrapper.closest('.yt-to-article-messages');
      if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      }
      
      // Limit to last 20 messages to prevent memory issues
      const messages = messagesWrapper.querySelectorAll('.yt-to-article-message');
      if (messages.length > 20) {
        messagesWrapper.removeChild(messages[0]);
      }
    },
    
    /**
     * Handle completion.
     */
    onComplete: function(data) {
      this.updateStatus('Article generated successfully!', 'completed');
      
      // Display final completion message
      this.displayMessage('Article generation completed successfully!', 'finished');
      
      // Fetch the node URL and display link
      this.fetchAndDisplayNodeLink();
      
      // Re-enable the submit button
      this.toggleSubmitButton(true);
      
      if (this.ws) {
        this.ws.close();
      }
    },
    
    /**
     * Fetch node URL by request ID and display link.
     */
    fetchAndDisplayNodeLink: function() {
      const requestId = this.requestId;
      const maxRetries = 5;
      let retryCount = 0;
      
      const tryFetchNode = () => {
        fetch('/yt-to-article/node/' + requestId)
          .then(response => response.json())
          .then(data => {
            const messagesContainer = document.querySelector('#yt-to-article-messages-container .yt-to-article-messages') || 
                                     document.querySelector('.yt-to-article-messages');
            
            if (messagesContainer) {
              // Check if actions already exist
              let actionsDiv = messagesContainer.querySelector('.yt-to-article-actions');
              if (!actionsDiv) {
                actionsDiv = document.createElement('div');
                actionsDiv.className = 'yt-to-article-actions';
                messagesContainer.appendChild(actionsDiv);
              }
              
              if (data.found && data.url) {
                // Successfully found the node
                actionsDiv.innerHTML = '<a href="' + data.url + '" class="button button--primary">' +
                  Drupal.t('View Article') + '</a>';
                this.displayMessage('Article ready to view: ' + data.title, 'success');
              } else if (!data.found && retryCount < maxRetries) {
                // Node not found yet, retry
                retryCount++;
                actionsDiv.innerHTML = '<div class="yt-to-article-loading">' + 
                  Drupal.t('Locating article... (attempt @count of @max)', {
                    '@count': retryCount,
                    '@max': maxRetries
                  }) + '</div>';
                setTimeout(tryFetchNode, 2000); // Retry after 2 seconds
              } else if (!data.found) {
                // Max retries reached
                actionsDiv.innerHTML = '<div class="messages messages--warning">' +
                  Drupal.t('Article is being processed. Please refresh the page in a moment.') +
                  '</div>';
                this.displayMessage('Article processing may still be in progress', 'warning');
              } else {
                // Error occurred
                actionsDiv.innerHTML = '<div class="messages messages--error">' +
                  Drupal.t('Unable to locate the article.') +
                  '</div>';
                console.error('[YtToArticle] Error fetching node:', data);
              }
            } else {
              console.error('[YtToArticle] Cannot find messages container for article link');
            }
          })
          .catch(error => {
            console.error('[YtToArticle] Error fetching node URL:', error);
            const messagesContainer = document.querySelector('#yt-to-article-messages-container .yt-to-article-messages') || 
                                     document.querySelector('.yt-to-article-messages');
            if (messagesContainer) {
              let actionsDiv = messagesContainer.querySelector('.yt-to-article-actions');
              if (!actionsDiv) {
                actionsDiv = document.createElement('div');
                actionsDiv.className = 'yt-to-article-actions';
                messagesContainer.appendChild(actionsDiv);
              }
              actionsDiv.innerHTML = '<div class="messages messages--error">' +
                Drupal.t('Error loading article link.') +
                '</div>';
            }
          });
      };
      
      // Start the first attempt
      tryFetchNode();
    },
    
    /**
     * Update connection status.
     */
    updateStatus: function(message, className) {
      const statusElement = document.querySelector('.yt-to-article-connection-status');
      if (statusElement) {
        statusElement.textContent = message;
        statusElement.className = 'yt-to-article-connection-status status-' + className;
      }
    },
    
    /**
     * Show error message.
     */
    showError: function(message) {
      // Use the displayMessage function to show errors
      this.displayMessage(message, 'error');
      
      // Re-enable submit button on error
      this.toggleSubmitButton(true);
    },
    
    /**
     * Toggle submit button state.
     */
    toggleSubmitButton: function(enable) {
      const submitButton = document.querySelector('.yt-to-article-submit');
      if (submitButton) {
        submitButton.disabled = !enable;
        if (enable) {
          submitButton.value = Drupal.t('Generate Article');
        } else {
          submitButton.value = Drupal.t('Generating... Please wait');
        }
      }
    }
    };
  };
  
  // Keep backward compatibility
  window.YtToArticleWebSocket = window.createYtToArticleWebSocket();

  /**
   * Drupal behavior for WebSocket initialization.
   */
  Drupal.behaviors.ytToArticleWebSocket = {
    attach: function (context, settings) {
      console.log('[YtToArticle] Behavior attached');
      console.log('[YtToArticle] Context:', context);
      console.log('[YtToArticle] Settings:', settings);
      console.log('[YtToArticle] drupalSettings:', drupalSettings);
      
      // Check both settings and drupalSettings
      const ytSettings = settings.ytToArticle || drupalSettings.ytToArticle;
      
      if (ytSettings && ytSettings.requestId) {
        console.log('[YtToArticle] Found requestId:', ytSettings.requestId);
        console.log('[YtToArticle] Found wsUrl:', ytSettings.wsUrl);
        console.log('[YtToArticle] Found token:', ytSettings.token ? 'yes' : 'no');
        
        // Use once to ensure we only attach once
        const elements = once('yt-to-article-websocket', 'body', context);
        console.log('[YtToArticle] Once elements:', elements);
        
        if (elements.length > 0) {
          console.log('[YtToArticle] Initializing WebSocket connection');
          // Update drupalSettings for the connect function
          if (!drupalSettings.ytToArticle) {
            drupalSettings.ytToArticle = ytSettings;
          }
          
          // Create a new instance for this request
          const wsInstance = window.createYtToArticleWebSocket();
          const requestId = ytSettings.requestId;
          
          // Store the instance
          window.YtToArticleWebSocketInstances.set(requestId, wsInstance);
          
          // Connect
          wsInstance.connect(requestId);
        } else {
          console.log('[YtToArticle] Already initialized, skipping');
        }
      } else {
        console.log('[YtToArticle] No ytToArticle settings found');
        console.log('[YtToArticle] Available settings keys:', Object.keys(settings));
        console.log('[YtToArticle] Available drupalSettings keys:', Object.keys(drupalSettings));
      }
    }
  };

})(Drupal, drupalSettings, once);