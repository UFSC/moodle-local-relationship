# Plano: hierarquia de branches, cascata por rebase, mirrors e aliases (master/main)

## Contexto

O projeto mantém o plugin compatível com várias versões do Moodle em paralelo, através de uma cadeia linear de branches por versão estável:

```
MOODLE_30_STABLE → MOODLE_31_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE
```

Quando uma alteração é aplicada em um branch da cadeia, os branches subsequentes precisam ser rebasedados sobre o anterior já atualizado, e o repositório é espelhado em **dois hosts** (GitLab UFSC e GitHub UFSC) que devem receber todos os pushes. Além disso, `master` e `main` são tratados como **aliases** de `MOODLE_30_STABLE` — sempre apontam para o mesmo commit. Ao terminar a cascata, voltar para o branch que estava ativo quando o commit foi solicitado.

Este `docs/branch-cascade.md` espelha o conteúdo da seção "Branch hierarchy and cross-version cascade" do `CLAUDE.md`. Sempre que a seção lá mudar, atualizar este arquivo em paralelo.

## Mirrors do repositório

O repo é mantido em dois hosts upstream. Todo push precisa atingir os dois:

- `origin_ufsc` → `git@gitlab.setic.ufsc.br:moodle-ufsc/moodle_local-relationship.git` (GitLab UFSC)
- `stream` → `git@github.com:UFSC/moodle-local-relationship.git` (GitHub UFSC)

Um terceiro remote, `origin`, aponta para o mesmo repo GitHub via HTTPS — pode ser ignorado nos pushes (pushar em `stream` já atualiza o GitHub).

## master e main: aliases de MOODLE_30_STABLE

`master` e `main` sempre apontam para o mesmo commit de `MOODLE_30_STABLE`. Não recebem commits diretamente, não participam da cascata de rebase. Quando `MOODLE_30_STABLE` mover, ambos seguem por **fast-forward** (`git merge --ff-only MOODLE_30_STABLE` + push normal) e são pushados nos dois mirrors. Se `MOODLE_30_STABLE` tiver sido reescrito (amend/rebase), o sync vira `git reset --hard MOODLE_30_STABLE` + `git push --force-with-lease`.

## Conteúdo da seção no CLAUDE.md

Reproduzindo a seção atual do CLAUDE.md:

> ## Branch hierarchy and cross-version cascade
>
> The plugin is maintained against several Moodle versions in parallel. The release branches form a strictly linear, ordered chain (oldest → newest):
>
> ```
> MOODLE_30_STABLE → MOODLE_31_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE
> ```
>
> `MOODLE_35_STABLE` exists on some remotes but is considered legacy — **not part of the cascade**.
>
> ### `master` and `main`: aliases for `MOODLE_30_STABLE`
>
> `master` and `main` are kept strictly aligned with `MOODLE_30_STABLE` — they always point at the exact same commit. They do **not** receive commits directly and are **not** part of the cascade chain. Whenever `MOODLE_30_STABLE` moves, both `master` and `main` must be fast-forwarded to it and pushed to both mirrors (see the cascade workflow below).
>
> ### Remotes (two mirrors)
>
> The repo is mirrored on two upstream hosts. Every push must reach **both**:
>
> - `origin_ufsc` → `git@gitlab.setic.ufsc.br:moodle-ufsc/moodle_local-relationship.git` (GitLab UFSC)
> - `stream` → `git@github.com:UFSC/moodle-local-relationship.git` (GitHub UFSC)
>
> A third remote, `origin`, points to the same GitHub repo as `stream` over HTTPS — ignore it for pushes; pushing to `stream` already updates GitHub.
>
> ### Cascade rule
>
> When a change lands on any branch in the chain:
>
> 1. Remember the originally-active branch so you can return to it at the end.
> 2. Push the branch where the commit landed to both mirrors.
> 3. **If** the commit landed on `MOODLE_30_STABLE`: fast-forward `master` and `main` to it and push both to both mirrors. (If the commit landed elsewhere, skip this step — `MOODLE_30_STABLE` did not move.)
> 4. For every branch *downstream* of where the commit landed (to the right of it in the chain), rebase it onto its immediately preceding chain neighbour, in order, and force-push to both mirrors.
> 5. Return to the branch you remembered in step 1.
>
> Example — a fix committed on `MOODLE_30_STABLE` (the base of the chain, so the full workflow runs):
>
> ```bash
> # 0. Remember where we started.
> ORIGINAL_BRANCH=$(git branch --show-current)
>
> # 1. Land the change on MOODLE_30_STABLE, push to both mirrors.
> git checkout MOODLE_30_STABLE
> # ... edit, git add, git commit ...
> git push origin_ufsc MOODLE_30_STABLE
> git push stream      MOODLE_30_STABLE
>
> # 2. master and main follow MOODLE_30_STABLE (fast-forward).
> git checkout master && git merge --ff-only MOODLE_30_STABLE
> git push origin_ufsc master
> git push stream      master
>
> git checkout main && git merge --ff-only MOODLE_30_STABLE
> git push origin_ufsc main
> git push stream      main
>
> # 3. Cascade downstream — each branch rebases onto the previous one as just updated.
> git checkout MOODLE_31_STABLE && git rebase MOODLE_30_STABLE
> git push --force-with-lease origin_ufsc MOODLE_31_STABLE
> git push --force-with-lease stream      MOODLE_31_STABLE
>
> git checkout MOODLE_38_STABLE && git rebase MOODLE_31_STABLE
> git push --force-with-lease origin_ufsc MOODLE_38_STABLE
> git push --force-with-lease stream      MOODLE_38_STABLE
>
> git checkout MOODLE_401_STABLE && git rebase MOODLE_38_STABLE
> git push --force-with-lease origin_ufsc MOODLE_401_STABLE
> git push --force-with-lease stream      MOODLE_401_STABLE
>
> # 4. Return to the original branch.
> git checkout "$ORIGINAL_BRANCH"
> ```
>
> If the change lands on a non-base branch (e.g., `MOODLE_31_STABLE`), skip step 2 — `MOODLE_30_STABLE` was not touched, so `master` and `main` are already in sync. Push only the branch where the commit landed, then cascade downstream from there, then return.
>
> ### Notes
>
> - Always cascade in order (`31 → 38 → 401`, never skip a hop). Each branch rebases onto the **previous chain branch as just updated**, not onto the branch where the original change was made.
> - Each branch must be pushed to **both** `origin_ufsc` and `stream` before moving on; never let one mirror lag behind the other across a cascade step.
> - `master` and `main` track `MOODLE_30_STABLE` by fast-forward (`git merge --ff-only MOODLE_30_STABLE` + plain `git push`) in the normal case. If `MOODLE_30_STABLE` was rewritten (e.g., amend or rebase of an existing commit), the alias branches need `git reset --hard MOODLE_30_STABLE` + `git push --force-with-lease` instead.
> - Always return to the branch you started on at the end of the cascade (step 5). Skipping this leaves you parked on `MOODLE_401_STABLE` (or whichever was last) and a follow-up session may accidentally continue work on the wrong branch.
> - Upstream branches (to the left of where you committed) are **not** updated automatically — backporting to older versions is a separate, explicit decision.
> - Prefer `git push --force-with-lease` over `git push --force` (or `-f`). It refuses to overwrite remote work that appeared since your last fetch, which is the usual collaboration hazard with force-pushes. Use the unsafer `--force` only when you have a specific reason and have confirmed no one else is working on the branch.
> - Resolve any conflicts during rebase the normal way (`git add` + `git rebase --continue`); do not abandon the cascade halfway — leaving downstream branches out of sync is the failure mode this rule exists to prevent.

## Verificação

1. Abrir `CLAUDE.md` e conferir que a seção "Branch hierarchy and cross-version cascade" reflete o conteúdo acima, com as quatro subseções (aliases, mirrors, cascade rule, notes).
2. `git rev-parse master MOODLE_30_STABLE main` retorna o mesmo SHA três vezes.
3. `git ls-remote origin_ufsc master main` e `git ls-remote stream master main` mostram o mesmo SHA dos dois lados.
4. Após uma cascata: cada `MOODLE_*_STABLE` em sync com seus respectivos remotes.

## Riscos e pontos de atenção

- **Reset destrutivo no master:** os commits únicos que existiam no master antes do alinhamento foram descartados (eles tinham equivalentes em `MOODLE_38_STABLE`). Não há tag de backup.
- **`main` é branch nova:** se algum CI/script externo esperava só `master`, pode precisar de ajuste — fora do escopo desta convenção.
- **Convenção textual, não enforcement:** a regra é descrita mas não há hook/automação que force a cascata, o sync de aliases ou o push duplo. Se for desejado, um hook futuro pode ser criado.
- **Idioma:** seção do `CLAUDE.md` em inglês; este `docs/branch-cascade.md` em português, como os demais planos no diretório.
