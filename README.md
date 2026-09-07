# 🏈 Atkins NFL Pool (`football.wallyatkins.com`)

> **Private NFL straight Pick'em and season-long Survivor pool web application.**  
> Powered by **WallyAuth SSO** and built using the **BMAD (Breakthrough Method for Agile AI-Driven Development)** methodology.

---

## 🌟 Features

- **WallyAuth OIDC Authentication**: Seamless single sign-on with Authorization Code Flow & PKCE (`S256`).
- **Weekly Straight Pick'em**: Straight-up winner selection across all weekly NFL matchups with individual kickoff lockouts and Monday Night Football tiebreaker score predictions.
- **Season-Long Survivor Pool**: Single weekly survival pick with season-wide team exclusivity constraints and elimination grading.
- **Automated Score Ingestion**: Background CLI sync (`bin/sync-nfl.php`) polling community sports feeds (ESPN Scoreboard API).
- **Admin-Verified Payment Workflow**: Fee-free weekly stakes tracking with commissioner Venmo & Cash App instructions, single-click payment approval dashboard, and eligibility gating.
- **Responsive Theme**: Strict design continuity with `wallyatkins.com` dark/light aesthetics and mobile ergonomics.

---

## 📁 Repository Structure

```
football/
├── .bmad/                       # BMAD core configs and manifests
│   ├── config.yaml
│   └── manifests/
├── docs/                        # Planning, architectural & story artifacts
│   ├── prd.md                   # Product Requirements Document
│   ├── architecture.md          # Architecture Blueprint & Data Specs
│   └── stories/                 # Sharded user stories for development
├── public/                      # Apache web root
│   ├── index.php                # Front controller
│   ├── assets/                  # Frontend styling & scripts
│   └── .htaccess                # Apache routing & security headers
├── db/                          # Database migrations
│   └── migrations/
├── src/                         # Application source code
│   ├── Auth/                    # OIDC PKCE client
│   ├── Controllers/             # Request handlers
│   ├── Services/                # Scoring & sports data services
│   └── Database/                # Dual-driver PDO wrapper
├── templates/                   # Server-rendered layouts
├── bin/                         # CLI utilities (sync-nfl.php, migrate.php)
└── tests/                       # PHPUnit test suites
```

---

## 🚀 Quickstart & Development

1. **Clone & Configure**:
   ```bash
   cp .env.example .env
   composer install
   ```

2. **Run Local Server**:
   ```bash
   php -S localhost:8080 -t public
   ```

3. **Run Tests & Linters**:
   ```bash
   composer test
   composer run lint
   ```

---

## 📜 Documentation

- [Product Requirements Document (PRD)](docs/prd.md)
- [Architecture Blueprint](docs/architecture.md)
- [BMAD Stories Directory](docs/stories/)
