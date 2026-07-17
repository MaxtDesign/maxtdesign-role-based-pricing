# STATE: maxtdesign-role-based-pricing
Updated: 2026-07-17 by session (shared-lib re-vendor survey)

## Identity
MaxtDesign Role-Based Pricing for WooCommerce. Slug `maxtdesign-role-based-pricing`, short code `rbp` (registry). Repo: `C:/maxt/projects/plugin/maxtdesign-role-based-pricing`, remote `MaxtDesign/maxtdesign-role-based-pricing` (public). Distribution: wp.org free. Current version: **1.1.4 on `main` (f8dae61), NOT yet released — wp.org live is 1.1.3** (SVN r3569410, 2026-06-11).

## Status
Stable free plugin, WP 7.0 / WC 10.8 / PHP 7.4 floors verified. `main` carries an unreleased 1.1.4 (memory-exhaustion fix: `count_users()` replaces per-role `get_users()` in `get_all_roles()`/`delete_custom_role()`; fatal on ~18k-user stores). Dev tooling (PHPUnit, PHPStan, phpcs annotations) landed via "readiness" batches. No shared libs vendored — **correct by design** (see Locked decisions).

## Locked decisions
- 2026-07-17 (nav handoff §5): RBP is **Tier-2 / opportunistic mounting** — it does NOT vendor suite-core. Mounts under the MaxtDesign menu via `class_exists('MdSuite_Admin')` when present; fallback stays the `woocommerce` submenu. Target slug `md-rbp` with redirect from `maxtdesign-role-pricing`. The old "predates the spine" carve-out is **retired** (main-file docblock still states it — rewrite during migration).
- 2026-07-17 (UI handoff §8): RBP admin re-platform is **L effort, own dedicated session** — kill 8.5KB legacy CSS → tokens/components, 17-AJAX CRUD → PRG doctrine, `prompt()`/`confirm()` → styled dialog/inline fields.
- Prefixes **LOCKED** at `MAXTDESIGN_RBP_` / `maxtdesign_rbp_` (both handoffs) — never migrate to `md_rbp_`.
- 2026-05-28: WC floor 7.0 (HPOS-era alignment). 2026-06-11: atomic SVN commits; agent may run `svn ci` with cached creds.

## Next actions
1. [operator] Decide when to release 1.1.4 to wp.org (memory-exhaustion fix is user-impacting; ride normal release flow, no emergency).
2. [session] Dedicated nav+UI migration session (L): opportunistic mounting + `md-rbp` + redirect + action link + full UI re-platform per both handoffs. `class_exists('MdSuite_*')` only from hooks — never include time (loader footgun).
3. [session] At next SVN release: move listing PNGs `trunk/assets/` → SVN-root `/assets/` (~1 MB zip bloat, operator-confirmed).
4. [operator] Pro/licensing: rule-10 `license-client` lib now exists; reconcile with the pre-lib planning in LICENSING-HANDOFF.md before any Pro build.

## External relationships
- Vendored libs: **NONE — by design** (Tier-2 wp.org standalone; re-vendor/bootstrap pass N/A, verified 2026-07-17 survey).
- Depends on WooCommerce (`Requires Plugins: woocommerce`).
- SSOT: maxtdesign.com plugin page (Plugin URI), `lib/plugins-data.ts` changelog sync; wp.org SVN checkout `C:/maxt/ops/wp-org-svn/maxtdesign-role-based-pricing/`.
- wp.org quirk: listing PNGs live in `trunk/assets/` (not SVN-root `/assets/`) — prepare-svn scripts skip them deliberately.

## Verification state
- PHPUnit: tests/Unit present, green as of readiness batch E (2026-07-17 era). PHPStan: level per phpstan.neon.dist + baseline, clean at batch E.
- PHP lint clean (5 files). Plugin Check: clean on shipped code as of 1.1.2 (5 known dev-workspace false positives — see project memory).
- Live QA: 1.1.3 verified on wp.org (zip inspected file-by-file, 2026-06-11). 1.1.4 NOT yet live-verified.
- Zero-footprint: frontend = one conditional CSS file; admin assets page-scoped. No JS files (inline admin JS — migration session will move to files).

## History
- `docs/LICENSING-HANDOFF.md` → pre-license-client Pro-planning handoff (2026-06); partially superseded by hard rule 10 / the shipped `maxtdesign/license-client` — reconcile before Pro work.
- Project memory (`~/.claude/projects/C--maxt-projects-plugin-maxtdesign-role-based-pricing/memory/`) → release workflow lore, deferred-1.2.0 list, SVN PNG quirk.

## Flags
- Main-file docblock (lines ~47-50) still asserts the retired "predates the spine" carve-out — stale doctrine, accurate behavior; fix in migration session.
- `docs/` was fully gitignored, conflicting with tracking-notes-standard §"git-committed"; fixed 2026-07-17 (`docs/*` + `!docs/STATE.md`). Other repos likely share this — mirrored to central improvement log.
