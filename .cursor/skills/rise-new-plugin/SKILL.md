---
name: rise-new-plugin
description: Scaffold a new RISE CRM plugin under plugins/ with index.php metadata, lifecycle hooks, routes, controllers, language files, and index.html stubs. Use when the user asks to create a new plugin, add an app feature as a plugin, or scaffold Plugin_Name following RISE conventions.
---

# RISE New Plugin

Scaffold features as RISE plugins. Do **not** edit core app files unless the user explicitly requires it.

Follow project rules `rise-customization` and `rise-plugins`. Mirror `plugins/Remember_Me` (hook-only) or `plugins/Google_Docs_Integration` (full UI module).

## Before coding

Confirm or infer:

1. **Folder name** — PascalCase / underscores, unique under `plugins/` (e.g. `Task_Reminders`)
2. **Scope** — hooks-only vs pages/menu/settings/DB
3. **Auth** — staff, client, or both; public pages use `App_Controller`, else `Security_Controller`

If the folder name is unclear, ask once; otherwise pick a clear name from the feature title.

## Checklist

Copy and track:

```
Plugin Progress:
- [ ] Folder + root index.php (guard + metadata + lifecycle)
- [ ] index.html in every subfolder
- [ ] Language english default_lang.php + custom_lang.php (prefixed keys)
- [ ] Helpers/Models/Controllers/Views/Config as needed
- [ ] Routes if controllers exist
- [ ] app_hooks actions/filters wired in index.php
- [ ] Install/uninstall SQL for any tables
- [ ] No core settings table writes for plugin config
```

## Naming

| Item | Pattern |
|------|---------|
| Folder / hook id | `Task_Reminders` |
| Table | `{db_prefix}task_reminders_...` |
| Lang keys | `task_reminders_...` |
| Helper file | `task_reminders_helper.php` |
| Functions | `task_reminders_*` |
| Namespaces | `Task_Reminders\Controllers`, `\Models`, `\Libraries` |

## Minimal scaffold (always)

Create at least:

```
plugins/Plugin_Name/
  index.php
  index.html
  Language/index.html
  Language/english/index.html
  Language/english/default_lang.php
  Language/english/custom_lang.php
```

Add folders only as needed: `Config/`, `Controllers/`, `Models/`, `Helpers/`, `Views/`, `Libraries/`, `Filters/`, `assets/`, `install/`.

For file templates, see [templates.md](templates.md).

## index.php rules

1. `defined('PLUGINPATH') or exit('No direct script access allowed');`
2. Metadata comment block only in this file
3. `require_once` helpers/filters if present
4. Register install / uninstall / activate / deactivate (update optional)
5. Wire all `app_hooks()->add_action` / `add_filter` here
6. Filters **must return** the first argument

## After scaffold

Tell the user:

1. Zip or open **Settings → Plugins** to install/activate if needed
2. Run any install SQL / reinstall if the plugin was already listed without tables
3. Clear caches if UI/lang does not show

## Anti-patterns

- Editing core views/controllers instead of hooks
- Writing plugin settings into the core `settings` table
- Loading CSS/JS on every page
- Unprefixed functions, tables, or lang keys
- Missing `index.html` in subfolders
- Omitting `return $first` from filter callbacks
