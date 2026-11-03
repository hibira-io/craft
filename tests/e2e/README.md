# End-to-end check

Run against a Craft 5 site with this plugin installed (path repository), a local Hibira/pusher-server on 127.0.0.1:6001, a `news` section with entry 4 at `/news/hello` whose template calls `craft.hibira.registerScript()` and marks `data-hibira-entry`, and two admins (`admin`, `mei`):

```bash
node tests/e2e/presence.cjs /tmp
```

It checks that both editors see each other on the entry page, that a save by one shows the reload prompt to the other, and that an open front-end page gets the "This page was updated" notice. Verified 2026-11-02 on Craft 5.11 (PostgreSQL 16, PHP 8.3).
