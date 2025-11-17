# Documentation Workflow

ParaGra uses [Zensical](https://pypi.org/project/zensical/) for static documentation generation.

## Layout

- `src-docs/` — Markdown sources, organized by topic
- `docs/` — Built HTML + assets served by GitHub Pages
- `zensical.toml` — Project configuration (nav, theme, markdown extensions)

## Local development

### Install uv (one-time setup)
```bash
curl -LsSf https://astral.sh/uv/install.sh | sh
```

### Preview documentation
Start a live-reload development server:
```bash
cd paragra-php
uvx zensical serve
```

Open http://127.0.0.1:8000 in your browser. Changes to `src-docs/` auto-reload.

### Build documentation
Regenerate the static site:
```bash
uvx zensical build --clean
```

This writes HTML to `docs/` and clears any stale files.

## Navigation structure

Navigation is defined explicitly in `zensical.toml` (not filesystem order):

```toml
nav = [
  { "Overview" = "index.md" },
  { "Getting Started" = [
      { "Installation" = "getting-started/installation.md" },
      { "Quickstart" = "getting-started/quickstart.md" }
  ]},
  # ...
]
```

**To add a new page:**

1. Create the markdown file in `src-docs/`
2. Add an entry to `zensical.toml` nav array
3. Run `uvx zensical build --clean`

## GitHub Actions deployment

`.github/workflows/docs.yml` automates deployment:

1. Installs uv via official installer script
2. Runs `uv tool run zensical build --clean`
3. Uploads `docs/` folder as Pages artifact
4. Deploys to GitHub Pages

**Trigger:**
- Push to `main` or `master` branch
- Manual workflow dispatch

**Requirements:**
- Repository Settings → Pages → Source: GitHub Actions
- Workflow permissions: Read + Pages write

## Theme configuration

ParaGra uses Material for MkDocs (modern variant):

```toml
[project.theme]
variant = "modern"
language = "en"
features = [
  "content.code.copy",
  "navigation.tabs",
  "search.highlight",
  # ...
]

[project.theme.palette]
scheme = "default"
primary = "indigo"
accent = "indigo"
```

## Markdown extensions

Enabled extensions (via PyMdown):

- **Code blocks**: Syntax highlighting, line numbers, copy button
- **Admonitions**: Info/warning/note callouts
- **Tabs**: Multi-language code examples
- **Tables**: GitHub-flavored markdown tables
- **Task lists**: Interactive checkboxes
- **Emoji**: `:emoji_name:` syntax

**Example admonition:**
```markdown
!!! note "Configuration tip"
    Use `PoolBuilder::PRESET_FREE` for zero-cost testing.
```

**Example tabbed code:**
```markdown
=== "PHP"
    ```php
    $paragra = ParaGra::fromConfig($config);
    ```

=== "CLI"
    ```bash
    php tools/pool_builder.php --preset=free-tier
    ```
```

## Contribution checklist

Before opening a PR:

1. **Write content** — Create or edit files in `src-docs/`
2. **Update navigation** — Add entries to `zensical.toml` if needed
3. **Build locally** — Run `uvx zensical build --clean`
4. **Preview** — Check output in `docs/` or via `uvx zensical serve`
5. **Commit both** — Commit changes to `src-docs/` AND `docs/`

Keep Markdown short and decisive. Use admonitions for warnings/notes.

## Troubleshooting

### Build fails with "nav entry not found"
- Check that file paths in `zensical.toml` match files in `src-docs/`
- Paths are relative to `src-docs/` directory

### Changes don't appear in built docs
- Run `uvx zensical build --clean` to clear cache
- Verify `docs/` contains updated files

### GitHub Pages shows 404
- Ensure repository Settings → Pages → Source is "GitHub Actions"
- Check workflow run completed successfully
- Wait 1-2 minutes for Pages deployment

## Next steps

- Read [CLI Tools](cli.md) for utility script documentation
- Review [Testing](../how-to/testing.md) for test workflow
- Explore [Material for MkDocs docs](https://squidfunk.github.io/mkdocs-material/) for advanced theme features
