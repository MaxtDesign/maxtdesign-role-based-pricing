# STATE: maxtdesign-role-based-pricing
Updated: 2026-07-17 by session (suite nav+UI migration shipped)

## Identity
MaxtDesign Role-Based Pricing for WooCommerce. Slug `maxtdesign-role-based-pricing`, short code `rbp` (registry). Repo: `C:/maxt/projects/plugin/maxtdesign-role-based-pricing`, remote `MaxtDesign/maxtdesign-role-based-pricing` (public). Distribution: wp.org free. Current version: **1.2.0 on `main` (f335f7a), NOT yet released — wp.org live is 1.1.3** (SVN r3569410, 2026-06-11). Unreleased on main: 1.1.4 memory fix + 1.2.0 suite migration.

## Status
The dedicated suite nav+UI migration (L item from both 2026-07-17 handoffs) SHIPPED 2026-07-17 as 1.2.0: screen is `md-rbp` (suite-mounted when suite-core active, WooCommerce submenu fallback, legacy-slug redirect), all mutations are admin-post PRG (18 AJAX handlers + ~500 lines inline jQuery + modal + prompt()/confirm() all removed — zero admin JS, CSP-clean), metabox rules persist via save_post_product, suite component shapes with a 3.5KB Tier-2 fallback stylesheet (was 8.5KB). Gates green (lint, PHPUnit 7/7, PHPStan clean, baseline net smaller). **Live click-through pending** (operator: LocalWP `plugin-test`, fallback mode). No shared libs vendored — correct by design (see Locked decisions).

## Locked decisions
- 2026-07-17 (nav handoff §5): RBP is **Tier-2 / opportunistic mounting** — it does NOT vendor suite-core. Mounts under the MaxtDesign menu via `class_exists('MdSuite_Admin')` when present; fallback stays the `woocommerce` submenu. Slug `md-rbp` with permanent redirect from `maxtdesign-role-pricing`. ✅ IMPLEMENTED in 1.2.0 (f335f7a).
- 2026-07-17 (UI handoff §8): full admin re-platform — legacy CSS → suite class shapes, AJAX CRUD → PRG doctrine, `prompt()`/`confirm()` → dialog contract/inline fields. ✅ IMPLEMENTED in 1.2.0. Metabox rules now persist on `save_post_product` (apply on product Update, not instantly — deliberate, the metabox-native PRG form).
- Prefixes **LOCKED** at `MAXTDESIGN_RBP_` / `maxtdesign_rbp_` (both handoffs) — never migrate to `md_rbp_`.
- 2026-05-28: WC floor 7.0 (HPOS-era alignment). 2026-06-11: atomic SVN commits; agent may run `svn ci` with cached creds.

## Next actions
1. [operator] Live click-through of 1.2.0 on LocalWP `plugin-test` (fallback mode): `md-rbp` page renders, legacy slug redirects, role create/delete, global rule add/edit/toggle/delete, cache clear/warm, metabox add/edit/delete rules on product Update. Then decide release timing (1.2.0 bundles the 1.1.4 memory fix; normal flow, no emergency).
2. [session] Suite-mode verification when convenient: activate any suite-core-vendoring plugin (e.g. signal) on a test site — RBP should appear under the MaxtDesign menu, styled by the suite stylesheet, and on the Overview registry.
3. [session] At next SVN release: move listing PNGs `trunk/assets/` → SVN-root `/assets/` (~1 MB zip bloat, operator-confirmed, 2026-06-11).
4. [operator] Pro/licensing: rule-10 `license-client` lib now exists; reconcile with the pre-lib planning in LICENSING-HANDOFF.md before any Pro build. The 1.2.0 `maxtdesign_rbp_admin_tabs` filter + `maxtdesign_rbp_render_tab_{slug}` action are the Pro tab-injection seams.

## External relationships
- Vendored libs: **NONE — by design** (Tier-2 wp.org standalone; re-vendor/bootstrap pass N/A, verified 2026-07-17 survey).
- Depends on WooCommerce (`Requires Plugins: woocommerce`).
- SSOT: maxtdesign.com plugin page (Plugin URI), `lib/plugins-data.ts` changelog sync; wp.org SVN checkout `C:/maxt/ops/wp-org-svn/maxtdesign-role-based-pricing/`.
- wp.org quirk: listing PNGs live in `trunk/assets/` (not SVN-root `/assets/`) — prepare-svn scripts skip them deliberately.

## Verification state
- PHPUnit 7/7 green, PHPStan clean (baseline regenerated, net smaller), PHP lint clean — all 2026-07-17 on the 1.2.0 migration.
- Plugin Check: clean on shipped code as of 1.1.2 (5 known dev-workspace false positives — see project memory). Re-run recommended before the 1.2.0 release.
- Live QA: 1.1.3 verified on wp.org (zip inspected file-by-file, 2026-06-11). 1.1.4 + 1.2.0 NOT yet live-verified (operator click-through pending — Next action 1).
- Zero-footprint: frontend = one conditional CSS file; admin = one conditional CSS file on own hook_suffix only (suite-absent only); **zero admin JavaScript as of 1.2.0**.

## History
- `docs/LICENSING-HANDOFF.md` → pre-license-client Pro-planning handoff (2026-06); partially superseded by hard rule 10 / the shipped `maxtdesign/license-client` — reconcile before Pro work.
- Project memory (`~/.claude/projects/C--maxt-projects-plugin-maxtdesign-role-based-pricing/memory/`) → release workflow lore, deferred-1.2.0 list, SVN PNG quirk.

## Flags
- ~~Main-file docblock carve-out~~ — resolved in 1.2.0 (docblock rewritten to the Tier-2 contract).
- `docs/` was fully gitignored, conflicting with tracking-notes-standard §"git-committed"; fixed 2026-07-17 (`docs/*` + `!docs/STATE.md`). Other repos likely share this — mirrored to central improvement log.
