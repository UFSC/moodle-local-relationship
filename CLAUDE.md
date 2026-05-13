# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`local_relationship` is a Moodle **local plugin** (component `local_relationship`). It introduces a new grouping abstraction that lives in a course-category context: a *relationship* aggregates one or more cohorts (each bound to a role and to capability `local/relationship:assign`) and divides their members into *relationship groups*, optionally with size limits and uniform distribution. The motivating use case is institutional pairings Moodle has no native model for, e.g. Tutor ↔ Student.

This repo is the plugin only. To function it must be checked out at `<moodle-root>/local/relationship/`. The surrounding working tree (`local-unasuscp/`) is the host Moodle install used for local development.

## Build / test commands

CI uses `moodle-plugin-ci` (see `.gitlab-ci.yml`, `.travis.yml`). The tool wraps Moodle's PHPUnit + Behat harness, installs a throwaway Moodle, and runs against MySQL or PostgreSQL.

```bash
# From the parent of the Moodle dir, install once per CI run:
composer create-project -n --no-dev moodlerooms/moodle-plugin-ci ci ^1
export PATH="$(cd ci/bin; pwd):$(cd ci/vendor/bin; pwd):$PATH"
moodle-plugin-ci install --db-user=root --db-pass=... --db-host=mysql

# Then, from the plugin directory:
moodle-plugin-ci phpunit
moodle-plugin-ci behat
```

For ad-hoc test runs against a pre-existing dev Moodle (the host at `local-unasuscp/`), use Moodle's own runners from the Moodle root:

```bash
# PHPUnit — single test class or method
php admin/tool/phpunit/cli/init.php           # one-time
vendor/bin/phpunit --filter test_method_name local/relationship/tests/...

# Behat
php admin/tool/behat/cli/init.php             # one-time
vendor/bin/behat --tags=@local_relationship
```

There are no PHPUnit tests checked in yet — only Behat features under `tests/behat/`. `behat_relationship.php` adds the custom step `the following "relationships" exist`.

## Architecture

### Data model (`db/install.xml`)

Four tables, all prefixed `relationship`:

- **`relationship`** — one per relationship instance; lives in a `CONTEXT_COURSECAT` (`contextid`). Tagged via core `tag_*` API.
- **`relationship_cohorts`** — join row binding a cohort + a role to a relationship. Flags: `allowdupsingroups`, `uniformdistribution`.
- **`relationship_groups`** — named subgroup with optional `userlimit` and its own `uniformdistribution` flag.
- **`relationship_members`** — actual `(relationshipgroupid, relationshipcohortid, userid)` membership rows (unique index on the triple).

### Layers

- **Page controllers** (top-level `.php` files): `index.php` lists, `edit.php` create/edit relationship, `cohorts.php` + `edit_cohort.php` manage attached cohorts, `groups.php` + `edit_group.php` manage groups, `assign.php` is the dual user-selector for manual membership, `autogroup.php` bulk-creates groups from cohort sizes. Each requires `local/relationship/lib.php` (data API) and `locallib.php` (UI helpers).
- **Data API** (`lib.php`): the CRUD functions `relationship_{add,update,delete,get}_{relationship,cohort,group,member}` plus the auto-distribution logic. **Every mutating function triggers an event** from `classes/event/` and adds record snapshots — preserve that pattern on any new mutation path.
- **UI helpers** (`locallib.php`): page chrome (`relationship_set_header`, `relationship_set_title`), role/cohort option builders, and the group-name formatter that supports `@` (letter series) and `#` (number series) tokens.
- **Forms** (`classes/form/`): moodleform subclasses for relationship/cohort/group edit dialogs and the autogroup wizard.
- **User selectors** (`classes/{candidate,existing}_selector.php`): power `assign.php`'s dual-list UI; extend Moodle's `user_selector_base`.
- **Observer + cron** (`classes/observer.php`, `db/events.php`, `local_relationship_cron`): listens for `core\event\cohort_member_{added,removed}` and `cohort_deleted` to keep `relationship_members` in sync. Cron re-runs `relationship_uniformly_distribute_members` as a safety net because event delivery can be missed. **Heads up:** `db/events.php` currently has a typo (`'eventname' => ' \core\event\cohort_deleted'` has a leading space and the callback uses `:` instead of `::`) — fix carefully if touching it; tests may not catch this.

### Capabilities (`db/access.php`)

All three are `CONTEXT_COURSECAT` and granted to `manager` by default:
- `local/relationship:view` — see the listing and members
- `local/relationship:manage` — CRUD on relationships, cohorts, groups
- `local/relationship:assign` — add/remove members

The settings-navigation hook in `lib.php` only shows the menu entry if `:manage` is held on the current course category context.

### Plugin metadata

`version.php` declares `requires = 2013111803` (Moodle 2.6+) and is on `MATURITY_BETA`. Bump `$plugin->version` (YYYYMMDDXX) for any schema or install/upgrade-affecting change.

## Conventions to follow

- The `relationship` tag is registered via `tag_set('relationship', ...)`. Keep tag handling routed through that itemtype.
- Group-name format strings use `@` → letter sequence, `#` → number sequence. See `relationship_groups_parse_name()` in `locallib.php`.
- User-visible strings live in `lang/en/local_relationship.php`. The README and many comments are in Portuguese; UI strings should stay English-only in this file and be translated via Moodle's AMOS workflow.
- Target PHP 5.5 / 5.6 syntax (Moodle 2.9 / 3.0 baseline) — no short array syntax in places that need to stay BC, no scalar type hints, no null coalescing.
