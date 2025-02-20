# Post Status Updater

[![WordPress](https://img.shields.io/badge/WordPress-v5.x-blue)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPLv2-green)](https://www.gnu.org/licenses/gpl-2.0.html)

A lightweight WordPress plugin to bulk update post statuses based on expected status and target status. The plugin allows you to input a list of URLs, specify the expected current status, and choose the new status to apply. It includes robust validation, error handling, and double-checking of changes to ensure accuracy.

---

## Features

- **Bulk Update Posts:** Update multiple posts at once by providing their URLs.
- **Status Validation:** Ensures posts have the expected status before applying changes.
- **Double-Checking Changes:** Verifies that the status change was successful after updating.
- **Conservative Error Handling:** Stops processing immediately if any issue is encountered.
- **User-Friendly Interface:** Provides clear feedback via success and error messages.
- **Secure:** Uses WordPress nonces to prevent CSRF attacks and sanitizes all inputs.

---
