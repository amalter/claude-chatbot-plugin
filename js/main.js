jQuery(document).ready(function ($) {
  console.log("chatbot.js loaded");
  const chatbot = {
    init: function () {
      this.container = $("#chatbot-widget");
      this.messagesContainer = $(".chatbot-messages");
      this.input = $(".chatbot-input-field");
      this.sendButton = $(".chatbot-send");

      this.bindEvents();
      this.addWelcomeMessage();
    },

    bindEvents: function () {
      // Toggle chatbot
      $(".chatbot-header").on("click", () => {
        this.container.toggleClass("minimized");
      });

      // Send message
      this.sendButton.on("click", () => this.sendMessage());

      // Enter key
      this.input.on("keypress", (e) => {
        if (e.which === 13) this.sendMessage();
      });

      // Input validation
      this.input.on("input", () => {
        this.sendButton.prop("disabled", !this.input.val().trim());
      });
    },

    addWelcomeMessage: function () {
      this.addMessage(
        "Hi! I'm your website assistant. How can I help you today?",
        "bot"
      );
    },

    addMessage: function (message, type, sources = []) {
      const messageHtml = `
                <div class="chat-message ${type}-message">
                    ${message}
                    ${this.formatSources(sources)}
                </div>
            `;

      this.messagesContainer.append(messageHtml);
      this.scrollToBottom();
    },

    formatSources: function (sources) {
      if (!sources || !sources.length) return "";

      const sourceLinks = sources
        .map(
          (source) =>
            `<a href="${source.url}" target="_blank">${source.title}</a>`
        )
        .join(", ");

      return `<div class="message-sources">Sources: ${sourceLinks}</div>`;
    },

    scrollToBottom: function () {
      this.messagesContainer.scrollTop(this.messagesContainer[0].scrollHeight);
    },

    sendMessage: function () {
      const message = this.input.val().trim();
      if (!message) return;

      this.addMessage(message, "user");
      this.input.val("");
      this.sendButton.prop("disabled", true);

      $.ajax({
        url: chatbotAjax.ajaxurl,
        method: "POST",
        data: { query: message },
        headers: {
          "X-WP-Nonce": chatbotAjax.nonce,
        },
        success: (response) => {
          this.addMessage(response.response, "bot", response.sources);
        },
        error: (xhr) => {
          this.addMessage(
            "I'm sorry, I encountered an error. Please try again later.",
            "bot"
          );
          console.error("Chatbot error:", xhr);
        },
      });
    },
  };

  // Initialize chatbot
  chatbot.init();
});
