/**
 * @file
 * Overrides for YT to Article WebSocket to display connection status in console.
 */

(function () {
  'use strict';
  
  // Wait for the WebSocket object to be available
  const waitForWebSocket = setInterval(function() {
    if (window.YtToArticleWebSocket) {
      clearInterval(waitForWebSocket);
      
      // Store the original updateStatus function
      const originalUpdateStatus = window.YtToArticleWebSocket.updateStatus;
      
      // Override updateStatus to display in console instead
      window.YtToArticleWebSocket.updateStatus = function(message, className) {
        // Call original function to maintain compatibility
        originalUpdateStatus.call(this, message, className);
        
        // Create a connection status message in the console
        const timestamp = new Date().toLocaleTimeString();
        const messageElement = document.createElement('div');
        messageElement.className = 'yt-to-article-message yt-to-article-message--status';
        messageElement.setAttribute('data-message-type', 'connection');
        messageElement.setAttribute('data-status', className);
        
        // Add timestamp
        const timeElement = document.createElement('span');
        timeElement.className = 'yt-to-article-message-time';
        timeElement.textContent = timestamp;
        
        // Add message text
        const textElement = document.createElement('span');
        textElement.className = 'yt-to-article-message-text';
        textElement.textContent = '[SYSTEM] ' + message;
        
        // Compose message
        messageElement.appendChild(timeElement);
        messageElement.appendChild(textElement);
        
        // Find the messages wrapper and add the message
        const messagesWrapper = document.querySelector('.yt-to-article-messages .messages__wrapper');
        if (messagesWrapper) {
          // Append connection messages at the bottom like other messages
          messagesWrapper.appendChild(messageElement);
          
          // Auto-scroll to bottom to show latest message
          const messagesContainer = messagesWrapper.closest('.yt-to-article-messages');
          if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
          }
        }
      };
      
      // Also override the initial connection display
      const originalConnect = window.YtToArticleWebSocket.connect;
      window.YtToArticleWebSocket.connect = function(requestId) {
        // Show connecting message in console
        this.updateStatus('Initializing WebSocket connection...', 'connecting');
        
        // Call original connect
        originalConnect.call(this, requestId);
      };
    }
  }, 100);
})();