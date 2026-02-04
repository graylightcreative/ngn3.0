1. Vision & Architecture

1.1 Executive Summary

NGN 2.0 represents a complete modernization of the NextGen Noise platform. The objective is to transition from a legacy, server-rendered PHP application to a modern, API-first architecture.

Core Philosophy:

Decoupling: The Frontend (Tailwind/JS) is completely decoupled from the Backend (PHP Service Layer). Communication happens strictly via JSON APIs.

Canonical Data: We move from ad-hoc legacy tables to a structured Canonical Data Model (CDM) that enforces integrity and normalizes data from disparate sources (Spins, SMR, Socials).

Role-Driven: The system is designed around specific personas: Artists, Labels, Station Owners, Venues, and Fans.

1.2 Technology Stack

Backend

Language: PHP 8.4+ (Strict typing, Service classes).

Database: MySQL 8.0 (InnoDB, utf8mb4 charset).

API: RESTful JSON API (v1). No mixed HTML/JSON responses.

Auth: JWT (JSON Web Tokens) with Role-Based Access Control (RBAC).

Frontend

Build Tool: Vite.

Styling: Tailwind CSS (JIT mode).

HTTP Client: Axios (Interceptors for Auth/Retry).

Architecture: Single Page Application (SPA) logic where possible, embedded in a lightweight PHP shell for initial routing if necessary during transition.

Infrastructure

Job Queue: Cron-driven PHP scripts (Rankings, Ingestion).

Storage: Local filesystem for assets (migrating to object storage compatibility).

1.3 System Topography

The "Spotify Killer" Theme

The UI/UX aims for a dark-mode, high-density dashboard aesthetic similar to Spotify, focusing on data visualization (Charts) and media playback.

Directory Structure (Target)

Based on the temp-structure.md audit, the system is organized as follows:

/
├── api/                 # Public JSON API Entry Point
│   └── v1/              # Version 1 Routes
├── frontend/            # Vite + Tailwind Source
│   └── src/             # JS/Vue/React components
├── lib/                 # Core Business Logic (Domain Layer)
│   ├── Domain/          # Entities (Artist, Spin, Post)
│   ├── Services/        # Business Rules (RankingService, IngestionService)
│   ├── DB/              # Database Abstraction
│   └── Utils/           # Helpers
├── jobs/                # Background Workers (Cron targets)
│   ├── ingestion/       # SMR/Spin ingestion
│   └── rankings/        # Score computation engines
├── docs/                # Documentation (The Bible)
└── storage/             # Logs, Cache, Uploads, Backups


1.4 Roadmap Summary

Reference: NGN-2.0-Roadmap.md

Phase 1: Architecture Restructure: Segregating API, Frontend, and Libs. (Current Status: In Progress)

Phase 2: Frontend 2.0: Implementing Tailwind + Axios shell.

Phase 3: API Design (v1): Standardizing endpoints.

Phase 6: Rankings 2.0: Implementing the new Scoring.md logic with Factors.json.

Phase 10: Commerce: Merch shops (Printful).

Phase 11: Ads Platform: Self-serve ad buying.

1.5 Compatibility Strategy

During the migration, the Legacy Database (nextgennoise) and CDM (cdm_* tables) will coexist.

Writes: New features write to CDM. Legacy adapters sync back if necessary.

Reads: API v1 reads from CDM. Legacy pages read from legacy tables until deprecated.

Cutover: Controlled via FEATURE_PUBLIC_VIEW_MODE flags.