## ReactifyWP Plugin Development Plan

### 1. Vision & Goals

| Aspect | Goal |
| ------ | ---- |
|        |      |

| **Purpose**          | Democratise React on WordPress: one-click deployment of any compiled React SPA/MPA without touching the theme or server. |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| **Target users**     | Agencies, freelancers, and WP site owners who need modern JS apps (dashboards, widgets, calculators, etc.) within WP.    |
| **Success criteria** | <50 sec average upload→embed cycle, zero console errors in Lighthouse, <1 s added TTFB per app.                          |

---

### 2. Functional Requirements

| ID  | Requirement                                                                                                              | Priority |                      |        |
| --- | ------------------------------------------------------------------------------------------------------------------------ | -------- | -------------------- | ------ |
| F1  | Admin Upload UI in `Settings ▸ ReactifyWP` that accepts `.zip` files containing a React production build.                | Must     |                      |        |
| F2  | Capture Project Name (unique slug) and Shortcode Name (defaults to slug) on upload.                                      | Must     |                      |        |
| F3  | Extract ZIP to `wp-content/uploads/reactify-projects/{blog_id}/{slug}`.                                                  | Must     |                      |        |
| F4  | Register `[reactify slug="project-slug"]` shortcode that enqueues JS/CSS and renders `<div id="reactify-{slug}"></div>`. | Must     |                      |        |
| F5  | Multiple projects coexist; each has its own asset versions & cache-busting.                                              | Must     |                      |        |
| F6  | Inline admin table listing all projects with edit, re-upload, download, delete.                                          | Must     |                      |        |
| F7  | Optional Gutenberg block for drag-and-drop insertion.                                                                    | Should   |                      |        |
| F8  | WP-CLI commands (\`reactifywp list                                                                                       | upload   | delete\`) for CI/CD. | Should |
| F9  | Multisite support (per-site isolation).                                                                                  | Must     |                      |        |
| F10 | Internationalisation (i18n) and RTL CSS handling.                                                                        | Should   |                      |        |
| F11 | Native Page Builder Widgets for Elementor, Beaver Builder, etc.                                                          | Should   |                      |        |
| F12 | Scoped Styles Option to isolate app styles via `all: revert;` wrapper.                                                   | Should   |                      |        |
| F13 | Multisite uploads and project data are isolated per `blog_id`.                                                           | Must     |                      |        |

---

### 3. Non-Functional Requirements

| Category            | Target                                                                                        |
| ------------------- | --------------------------------------------------------------------------------------------- |
| **Security**        | File type whitelisting, ZIP bomb guard, nonce & capability checks, path traversal prevention. |
| **Performance**     | Asset versioning, defer load, optional inline critical CSS.                                   |
| **Compatibility**   | PHP 7.4+, WP 6.5+, major caching plugins, popular themes.                                     |
| **Accessibility**   | Admin UI WCAG 2.1 AA; embedded app respects ARIA roles.                                       |
| **Maintainability** | PSR-12 PHP, ESLint/Prettier JS, 85% test coverage, typed JS.                                  |

---

### 4. High-Level Architecture

```
reactifywp/
├─ reactifywp.php
├─ inc/
│  ├─ class-admin.php
│  ├─ class-project.php
│  ├─ class-shortcode.php
│  ├─ class-cli.php
│  ├─ integrations/
│  │  ├─ class-elementor.php
│  │  └─ class-beaver-builder.php
uploads/
└─ reactify-projects/
   └─ {blog_id}/
      └─ {slug}/
          ├─ index.html
          ├─ static/
          │  ├─ js/*.js
          │  └─ css/*.css
          └─ asset-manifest.json
```

---

### 5. Detailed Component Design

#### 5.1 Admin Settings & Upload Flow

- Form for `.zip`, `project_name`, `shortcode`.
- Validate and unzip safely to: `wp_upload_dir()['basedir']/reactify-projects/{blog_id}/{slug}`.
- DB table `wp_reactify_projects` with `blog_id`, `slug`, `shortcode`, `path`, `version`.

#### 5.2 Shortcode Rendering

- Uses `wp_get_upload_dir()` to construct asset URLs.
- Example:

```php
$upload_dir = wp_get_upload_dir();
$base_url = trailingslashit($upload_dir['baseurl']) . "reactify-projects/{$blog_id}/{$slug}";
return "<div class='reactify-scope reactify-{$slug}' style='all: revert;'><div id='reactify-{$slug}'></div></div>";
```

- React app must mount to dynamic ID: `reactify-{slug}`.

#### 5.3 Gutenberg Block

- Uses `@wordpress/scripts`.
- Dynamic dropdown of slugs.
- Saves: `<div data-reactify-slug="slug"></div>`.

#### 5.4 REST API & CLI

- `wp-json/reactify/v1/projects`
- `wp reactifywp upload myapp.zip --slug=myapp --shortcode=myapp`

#### 5.5 Page Builder Widgets

- Conditional loading via `did_action('elementor/loaded')`, `class_exists('FLBuilder')`.
- Elementor Widget:
  - Extends `\Elementor\Widget_Base`.
  - Dropdown of slugs.
  - Outputs `[reactify slug="..."]` in `render()`.
  - `get_script_depends()` and `get_style_depends()` ensure proper editor loading.
- Live editor: use JS hook `elementor/frontend/init` to mount React.

---

### 6. Security Checklist

| Threat            | Mitigation                         |
| ----------------- | ---------------------------------- |
| ZIP bombs         | Limit size, scan nested entries.   |
| Path traversal    | Reject `..`, sanitize filenames.   |
| XSS via shortcode | Sanitize all attrs, escape output. |
| CSRF              | Nonces in forms and REST routes.   |
| Permissions       | `manage_options` check.            |

---

### 7. Performance & Caching

- Use version hash for cache busting: `md5(filemtime + size)`.
- Defer JS, optional inline critical CSS.
- Optional shared React core support.

---

### 8. CI/CD

| Stage | Tools                                   |
| ----- | --------------------------------------- |
| Lint  | PHPStan, ESLint                         |
| Test  | PHPUnit, Jest                           |
| E2E   | Playwright                              |
| CI    | GitHub Actions (PHP matrix, deploy ZIP) |

---

### 9. Documentation

- User: upload + embed walkthrough.
- Developer: build structure, PUBLIC\_URL usage.
- Readme.txt, i18n `.pot` file, help tabs.

---

### 10. Timeline

| Week | Milestone                              |
| ---- | -------------------------------------- |
| 1    | Repo bootstrap, tooling setup          |
| 2    | Admin UI, DB schema                    |
| 3    | Upload, validation, extraction         |
| 4    | Shortcode, manifest parsing            |
| 5    | Gutenberg block, test coverage         |
| 6    | Elementor widget, scoped styles option |
| 7    | Multisite testing, finalize docs       |
| 8    | QA, hardening, release to wp.org       |

---

### 11. Team Roles

| Role      | Responsibility              |
| --------- | --------------------------- |
| Tech Lead | Architecture, CI/CD         |
| Devs      | PHP, React, integrations    |
| QA        | Cross-builder tests         |
| Docs      | Readme, guides, screencasts |

---

### 12. Risks & Mitigation

| Risk                      | Likelihood | Impact | Mitigation                                                 |
| ------------------------- | ---------- | ------ | ---------------------------------------------------------- |
| Elementor preview fails   | High       | High   | Hook `elementor/frontend/init`, expose `ReactifyWP.init()` |
| Theme styles leak         | High       | Medium | `all: revert;`, doc best practices                         |
| ZIP extract fails on host | Medium     | High   | Check `ZipArchive`, fallback warning                       |

---

### 13. Future Enhancements

- SSR (Node or PHP‑V8Js) support.
- GitHub Deploy Hook.
- Shadow DOM embedding.
- Role-based app access.

---

### 14. Sample User Story

> As a content editor, I upload `calculator.zip`, set project name `loan-calc`, and get `[reactify slug="loan-calc"]`. I paste it into my post, and the React calculator works with styling intact.

---

### 15. Next Steps

1. Review and approve final plan
2. Create repo, lock scope
3. Begin sprint 1

