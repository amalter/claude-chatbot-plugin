# Claude Chatbot Plugin (Proof of Concept)

⚠️ **Security Warning: This plugin is not production-ready. See warnings below.**

## Overview

This is a **proof-of-concept WordPress plugin** that integrates with the [Claude API](https://www.anthropic.com/claude) to create a simple, content-aware chatbot interface. The plugin is designed to demonstrate how AI-driven chat interactions can be embedded into WordPress sites using shortcodes and AJAX.

The chatbot attempts to provide contextually aware responses by leveraging the site's content and Anthropic's Claude API. It was built to explore:

- The process of integrating AI models into WordPress
- How to structure a lightweight chat interface in a plugin
- The use of prompt engineering in development
- The capabilities and limitations of using AI-generated code as a baseline

This plugin was primarily created as a learning exercise, using [Cursor](https://www.cursor.so/) and Claude to assist in development.

📖 Read more about the process and what I learned on my [blog](https://www.amberalter.com/ai-driven-development-chat-bot/).

---

## ⚠️ Security Warning

This project is **not secure** and should **not** be used in production environments without significant hardening. Known issues include:

- ❌ No input validation or sanitization
- ❌ No authentication or access controls
- ❌ API keys stored insecurely
- ❌ Potential for data exposure and misuse

It is intended for **learning and experimentation only**. Use at your own risk.

---

## Features

- Embeds a chat interface via a front-end container and script injection
- Basic front-end interface for user interaction
- AJAX-powered communication with a custom PHP endpoint
- API integration with Claude (Chat Completions endpoint)
- Rough implementation of content awareness (e.g., passing page content as part of the prompt)

---

## Tech Stack

- PHP (WordPress plugin framework)
- JavaScript (front-end chat + AJAX)
- HTML/CSS (chat UI)
- Claude API (Anthropic)

---

## Getting Started

1. Clone or download this repo into your WordPress `wp-content/plugins` directory.
2. Add your Claude API key to the appropriate section in the PHP file.
3. Activate the plugin in your WP admin.
4. Use the `[claude_chatbot]` shortcode to display the chatbot interface on any page or post.

> Note: This setup is intentionally simple and insecure — **do not deploy without serious modification**.

---

## 🔧 Future Improvements

- [ ] Sanitize and validate all user input
- [ ] Implement nonce-based verification for AJAX requests
- [ ] Move API keys to environment variables or use WordPress secrets management
- [ ] Add user authentication and permission checks (e.g., only show chatbot to certain roles)
- [ ] Escape output to prevent XSS vulnerabilities
- [ ] Refactor for shortcode support
- [ ] Add options page in the WP admin for managing API keys and settings
- [ ] Improve error handling and user feedback in the UI
- [ ] Make front-end styles and markup more customizable (via filters or templates)
- [ ] Add session context or memory for ongoing conversations
- [ ] Log or store anonymized chat data for learning purposes (with opt-in)
- [ ] Write unit and integration tests
- [ ] Optimize prompt strategy for better contextual accuracy

---

## License

This code is provided _as-is_, without warranty of any kind. It is intended for educational use only.

---

## Credits

Built with the help of:

- [Claude](https://www.anthropic.com/claude) – AI model
- [Cursor](https://www.cursor.so/) – AI pair programming editor

---

## Author

Amber Alter  
[amberalter.com](https://www.amberalter.com)
