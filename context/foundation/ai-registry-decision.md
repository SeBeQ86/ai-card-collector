# AI Registry — Decyzja dystrybucji artefaktów

## Zadanie 1 — Wybór modelu

### Kto jest odbiorcą?

Projekt AI Card Collector to narzędzie jednoosobowe — jedyny odbiorca to właściciel repo. Nie ma zespołu, nie ma zewnętrznych konsumentów. Tym niemniej, gdyby projekt rozrósł się do małego zespołu (2–5 osób), odbiorcą byłby **zespół na GitHubie**: wszyscy pracują w tym samym repo lub tej samej organizacji GitHub, korzystają z Claude Code jako podstawowego narzędzia AI.

### Wybrany model: Model 1 — GitHub Packages

**Uzasadnienie (2–3 zdania):**
Wszyscy potencjalni odbiorcy siedzą na GitHubie, więc GitHub Packages nie wymaga żadnej dodatkowej infrastruktury — wystarczy jedno pole `publishConfig` w `package.json`. Jedynym kosztem jest zarządzanie tokenem dostępu w CI, co przy repozytoriach w tej samej organizacji redukuje się do efemerycznego `GITHUB_TOKEN`. Modele 2 i 3 byłyby tutaj dystrybucją pod CV: AWS CodeArtifact i własne API+CLI mają sens przy wielozespołowej organizacji lub dawkowaniu treści w czasie — żadne z tych wymagań tu nie zachodzi.

### Dlaczego NIE model 2 ani 3?

| Kryterium | Stan projektu | Wniosek |
|---|---|---|
| Odbiorcy na AWS | ❌ brak | Model 2 odpada |
| Różne stacki technologiczne | ❌ tylko PHP + Node | Model 3 zbędny |
| Dawkowanie treści w czasie | ❌ nie potrzeba | Model 3 zbędny |
| Zewnętrzni odbiorcy bez GitHub | ❌ brak | Model 1 wystarczy |

---

## Zakres artefaktów AI w tym projekcie

Artefakty, które warto dystrybuować (gdyby był zespół):

| Typ | Plik | Opis |
|---|---|---|
| Skill | `.claude/skills/10x-plan/SKILL.md` | Planowanie zmiany z repo-map |
| Skill | `.claude/skills/10x-research/SKILL.md` | Research w legacy code |
| Rules | `CLAUDE.md` | Reguły projektu (stack, security, granice MVP) |
| Config | `.github/workflows/code-review.yml` | Pipeline z AI review |

---

## Schemat repozytorium źródła prawdy (Model 1)

Gdyby projekt rozrósł się do paczki dystrybuowanej przez GitHub Packages:

```
ai-toolkit/
├── package.json              # publishConfig → npm.pkg.github.com
├── install.js                # postinstall: kopiuje artefakty, dopisuje .npmrc
├── uninstall.js              # czyta manifest, usuwa dokładnie to, co dodał
├── skills/
│   └── code-review/
│       └── SKILL.md          # skill z M5L2/M5L3
├── rules/
│   └── CLAUDE.md             # reguły projektu jako blok sentinel
└── .github/
    └── workflows/
        └── publish-ai-toolkit.yml
```

Instalator dopisuje do `CLAUDE.md` konsumenta blok między znacznikami:
```
<!-- BEGIN @przeprogramowani/ai-toolkit -->
... reguły z paczki ...
<!-- END @przeprogramowani/ai-toolkit -->
```

Własne notatki dewelopera poza znacznikami przeżywają każdy `npm install`.
