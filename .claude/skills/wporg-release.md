# MaxtDesign Role-Based Pricing for WooCommerce — wp.org Release Skill

Project-specific wp.org release context for the procedure defined in `hq/.claude/agents/development/wporg-release.md`. Copy this file into a new plugin at `<plugin>/.claude/skills/wporg-release.md` and fill in the placeholders.

## Plugin Identity

| Field | Value |
|---|---|
| `wporg_slug` | `maxtdesign-role-based-pricing` |
| wp.org page | https://wordpress.org/plugins/maxtdesign-role-based-pricing/ |
| SVN repo | https://plugins.svn.wordpress.org/maxtdesign-role-based-pricing/ |
| Git repo | https://github.com/MaxtDesign/maxtdesign-role-based-pricing |
| `git_dir` | `~/maxtventures/plugins/maxtdesign-role-based-pricing/` |
| Main plugin file | `maxtdesign-role-based-pricing.php` |
| Text domain | `maxtdesign-role-based-pricing` |

**Note on `git_dir`:** Most plugins live at `~/maxtventures/plugins/<slug>/`. If this plugin lives elsewhere (e.g. inside another project's tree), set the path above and pass `--git-dir` to the release script. Example for mantlewp-connector: `git_dir = ~/maxtventures/web/mantlewp/mantlewp-connector/`.

## wp.org Distribution Flag
- [x] **Eligible for wp.org SVN procedure** — this plugin is distributed through wordpress.org and follows the standard release flow.
- [ ] Private / client-only — do NOT run the wporg-release procedure against this plugin.

## Version Source(s) of Truth
Every release requires these to match exactly:

| Location | File | Line/Pattern |
|---|---|---|
| Plugin header | `maxtdesign-role-based-pricing.php` | line 6: `Version: 1.1.0` |
| Readme stable tag | `readme.txt` | line 7: `Stable tag: 1.1.0` |
| Constant (if used) | `maxtdesign-role-based-pricing.php` | line 28: `define('MAXTDESIGN_RBP_VERSION', '1.1.0');` |
| Git tag | repo | `vX.Y.Z` on main |
| SVN tag | `/tags/X.Y.Z/` | created by procedure |

## Readme.txt Requirements
- `Tested up to:` current WP major (update every release)
- `Requires at least:` minimum supported WP — document here: `6.2`
- `Requires PHP:` minimum PHP — document here: `7.4`
- `== Changelog ==` has an entry for the new version before release
- Screenshots numbered to match files in `.wporg-assets/`

## Release Assets (wp.org `/assets/` directory)

Source files live in `.wporg-assets/` in the Git repo. Published separately from code via the asset-only procedure.

| Asset | File | Status |
|---|---|---|
| Banner hi-DPI | `banner-1544x500.png` | [current/needs update] |
| Banner standard | `banner-772x250.png` | [current/needs update] |
| Icon hi-DPI | `icon-256x256.png` | [current/needs update] |
| Icon standard | `icon-128x128.png` | [current/needs update] |
| Screenshots | `screenshot-N.png` | [count and status] |

## Distignore Specifics
Baseline comes from `hq/.claude/standards/wporg-svn-setup.md`. Additional per-plugin excludes:

```
# (none — org template at repo root)
```

## Release History

| Version | Date | Git SHA | Notes |
|---|---|---|---|
| _(add on each release)_ | | | |

## Plugin-Specific Notes / Gotchas

- Seeded 2026-04-16 via kickoff-prompts/wporg-release-rollout.md

## Local Testing (LocalWP)

This plugin should be junction-linked to the LocalWP `plugin-test` site so edits are live-testable in WordPress without copy/deploy.

**Junction status:** `linked to default path`

Create/refresh junction:
```
# Standard (plugin at ~/maxtventures/plugins/<slug>/)
powershell -File hq/.claude/scripts/symlink-plugin-to-localwp.ps1 -Slug maxtdesign-role-based-pricing

# Custom git_dir (override source)
powershell -File hq/.claude/scripts/symlink-plugin-to-localwp.ps1 `
  -Slug maxtdesign-role-based-pricing `
  -SourceDir "[full-path-to-git-repo]"
```

Protocol: agents working on this plugin run `-ListAll` first to confirm the junction exists. If not, create it. When a phase of work completes, activate the plugin in WP admin and smoke test before marking done.

## Quick Reference

Run release (standard plugin):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-role-based-pricing X.Y.Z
```

Run release (custom git_dir):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-role-based-pricing X.Y.Z --git-dir [path]
```

Asset-only update:
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-role-based-pricing --assets-only
```

Dry run (no commit):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-role-based-pricing X.Y.Z --dry-run
```

Full procedure details: `hq/.claude/agents/development/wporg-release.md`
