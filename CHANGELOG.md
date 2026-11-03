# Release Notes for Hibira Realtime

## 0.1.0 - 2026-11-02

- Editing presence on entry pages: who else has the entry open, and a reload prompt when someone else saves it.
- Live entries: `entry-updated` on `craft-entry-{id}` and `entry-saved` on `craft-section-{handle}` when a live entry is saved.
- Commerce: order status changes on `private-craft-order-{number}`, new order alerts in the control panel.
- `craft.hibira.trigger()`, `craft.hibira.registerScript()` and `data-hibira-entry` reload notices for templates.
- `actions/hibira/auth` signs private and presence channels, with `AuthController::EVENT_AUTHORIZE_CHANNEL` for your own.
- Control panel strings in English, 繁體中文, 简体中文, 日本語, 한국어, ภาษาไทย and Tiếng Việt.
