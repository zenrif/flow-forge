# ⚙️ Flow Forge - Real-Time Workflow Orchestration Engine (MVP)

## 📌 Overview
Flow Forge is a simplified, self-hosted workflow orchestration engine built to parse, sort, and execute Directed Acyclic Graph (DAG) definitions. 

Due to the strict 4-day timeframe constraints while balancing full-time employment, I made a strategic engineering decision to prioritize the **Core Execution Engine, DAG Parsing logic, and API Layer** over the frontend visualizer. The goal was to ensure the "brain" of the application is robust, strictly validated, and highly available.

## 🏗️ Architecture & Technical Decisions

1. **Modular Monolith & Dockerization:** To accelerate local setup and prevent CORS overhead during the MVP phase, I opted for a Modular Monolith architecture orchestrated via Docker (`docker-compose`). The backend API is strictly decoupled from the UI logic, allowing for an easy transition to a microservices architecture in the future.
2. **DAG Processing Strategy (Kahn's Algorithm):** Workflows are treated as strictly directed acyclic graphs. The `TopologicalSorter` implements Kahn's Algorithm to validate cycles and organize steps into "waves" for parallel execution.
3. **Execution Engine via Queues:** Instead of reinventing a concurrent execution pool, I leveraged Laravel's Queue system (`AdvanceWorkflowJob` and `ExecuteStepJob`) to handle step execution, utilizing its native exponential backoff for the required retry logic.
4. **Single-Database Multi-Tenancy:** To maintain infrastructure simplicity within the MVP timeframe, tenant isolation is handled via a single PostgreSQL database with global scopes, rather than complex multi-database routing.
5. **Real-time Broadcasting Strategy:** The system emits `StepStatusChanged` and `WorkflowRunStatusChanged` events. While the frontend UI consumption is incomplete, the backend is fully wired to use Pusher (or Laravel Reverb) for seamless WebSocket integration.

## ✅ Completed Features (Phase 1 & 2)

### 1. Multi-Tenant API & Security
* JWT-based Authentication (`php-open-source-saver/jwt-auth`).
* Base CRUD for Workflows with tenant-isolated database architecture.
* Input validation and sanitization via Laravel Form Requests.

### 2. Core DAG Engine (`app/Services/Workflow/DAG/`)
* **`DagParser.php`**: Validates workflow structure and JSON payloads.
* **`TopologicalSorter.php`**: Detects infinite loops (cycle detection) and generates execution order.
* **Custom Exception Handling**: `DagValidationException`, `StepExecutionException`, `WorkflowTimeoutException`.

### 3. Workflow Executors & Orchestration (`app/Jobs/Workflow/`)
* **`StartWorkflowRunJob.php`**: The entry point that parses the initial workflow state.
* **`AdvanceWorkflowJob.php`**: The central orchestrator that determines which step to execute next based on the DAG dependencies.
* **`ExecuteStepJob.php`**: Handles the actual step execution using polymorphism (`HttpStepExecutor`, `ScriptStepExecutor`, etc.) and manages configurable retry logic with exponential backoff.

## ⏳ Known Limitations & Incomplete Features

As an MVP delivered within a 4-day window, the following features were scoped out and marked for future iterations:
* **Frontend SPA & Real-time Visualizer:** The `resources/js` structure (Vue 3, Pinia, Vue Flow) is scaffolded, but the UI binding to the backend endpoints is incomplete.
* **AI Enhancements:** The LLM failure analysis API integration was deprioritized to focus on core DAG stability.
* **Dedicated High-Volume Log Store:** Currently, execution logs are stored in the relational PostgreSQL database. In a production environment, this would be migrated to an append-only NoSQL store (e.g., ClickHouse or MongoDB) to prevent read/write bottlenecks.

## 🚀 Setup & Execution

### Prerequisites
* Docker & Docker Compose

### Installation Steps
1. Clone the repository.
2. Copy the environment file: `cp .env.example .env`
3. Spin up the containers:
   ```bash
   docker-compose up -d --build
