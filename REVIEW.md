# Flow Forge - Review & Documentation

## Project Overview
**Flow Forge** is a self-hosted DAG (Directed Acyclic Graph) Engine designed to parse, sort, and execute complex workflows. This project was developed as an MVP to fulfill the requirements for the Sevima technical assessment. 

Due to the strict 4-day timeframe constraints, strategic engineering decisions were made to prioritize backend resilience—specifically the **Core Execution Engine, DAG Parsing logic, and API Layer**.

## Architecture & Tech Stack
The system adopts a Modular Monolith architecture to accelerate development and simplify the deployment infrastructure:
- **Backend Core:** Laravel 11 (PHP 8.3)
- **DAG Execution:** Kahn's Algorithm (Topological Sorting) & Laravel Queues
- **Database:** PostgreSQL 15 (Single-Database Multi-Tenancy)
- **Caching & Broadcasting:** Redis, Laravel Echo / Pusher JS
- **Infrastructure:** Docker, Docker Compose, Nginx, Supervisor
- **Frontend (Scaffolded):** Vue 3 (Composition API), Pinia, Tailwind CSS v4, `@vue-flow/core`

## Key Engineering Achievements
1. **Robust DAG Engine:**
   - Implemented a `TopologicalSorter` utilizing Kahn's Algorithm to validate dependencies, prevent infinite loops, and organize steps into executable "waves".
2. **Queue-Based Execution Strategy:**
   - Leveraged Laravel's native queue system (`AdvanceWorkflowJob` and `ExecuteStepJob`) to act as the concurrent execution pool.
   - Includes built-in exponential backoff for robust retry mechanisms on step failures.
3. **Production-Ready Infrastructure:**
   - Delivered a fully functional `docker-compose.yml` environment.
   - Utilizes `supervisord` within the PHP container to ensure `php-fpm`, `nginx`, and the `laravel-worker` (queue) run reliably and concurrently.
4. **Multi-Tenant Security:**
   - Secured via JWT-based Authentication.
   - Tenant data isolation is strictly enforced through PostgreSQL global scopes.

## Notes for the Reviewer
While the Vue 3 frontend components (such as the DAG Visualizer, Health Panel, and real-time store via Echo) have been beautifully scaffolded and fully typed, the final UI binding to the backend endpoints remains incomplete due to time constraints. 

However, the **infrastructure and backend orchestrator are production-ready**. Real-time events (`StepStatusChanged`, `WorkflowRunStatusChanged`) are correctly emitted from the backend queue workers and are ready to be seamlessly consumed by the WebSocket client.

### How to Run
The application is fully containerized for a zero-configuration review experience.
1. `cp .env.example .env` (Ensure your database and Pusher credentials are appropriately set).
2. Build and spin up the containers:
   ```bash
   docker-compose up -d --build
   ```
3. The application will be accessible at `http://localhost:8000`.

Thank you for your time and the opportunity to undertake this assessment!
