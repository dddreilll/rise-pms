# RISE CRM Customization

Prefer **plugins** over editing core RISE files. Updates overwrite core; plugins and the paths below survive upgrades.
Docs: https://risedocs.fairsketch.com/doc/view/60-development-customization

## Safe customization paths

- **CSS:** `assets/css/custom-style.css` (clear browser cache after changes)
- **JS:** `app/Views/includes/custom_head.php`

## Controllers & data

- Login-required pages → extend `Security_Controller`
- Public pages → extend `App_Controller`
- Models → extend `Crud_model` (`app/Models/Crud_model.php`) for add/edit/remove and basic I/O
- Stack is CodeIgniter 4; use CI docs for advanced patterns

## Language

Docs: https://risedocs.fairsketch.com/doc/view/58-manage-language-translations-and-customize-text

- Never edit `default_lang.php` — updates overwrite it
- Put overrides in `custom_lang.php`
- App: `app/Language/{lang}/`; plugins: `plugins/Plugin_Name/Language/{lang}/`
- Retrieve with `app_lang("key")`; prefix plugin keys (e.g. `remember_me_...`)

## Extension preference

When unsure, extend via `app_hooks()` (actions/filters) rather than patching core controllers or views.
If a hook fires too late for the need (e.g. auth/session state must be restored before `Security_Controller`
redirects), a CodeIgniter Filter registered in the plugin's `index.php` is a valid escape hatch — see
`plugins/Remember_Me/index.php` (`RememberMeFilter`) for the pattern.
Plugin conventions: see `docs/plugin-development.md` and https://risedocs.fairsketch.com/doc/category/5
