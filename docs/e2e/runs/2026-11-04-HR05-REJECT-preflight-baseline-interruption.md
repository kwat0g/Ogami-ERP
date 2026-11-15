# HR05-REJECT — preflight: E2E account seed interrupted

The dedicated E2E stack started under `ogami-erp-e2e-session`; E2E Redis flushes completed and `migrate:fresh --force --seed` completed. The subsequent `E2EAccountSeeder` exec exited 137 while the Compose services received stop signals. No browser or HR-05 process action ran, and the baseline was treated as incomplete.

- **Classification:** Environment issue, tracked by [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated).
- **Evidence:** The account-seeder command returned exit 137; Docker events show the temporary E2E Compose services stopped during the invocation. The shutdown source and OOM status were not established.
- **Follow-up:** A later E2E startup and account-seed command reported all 37 isolated actors created with no transactional demo records. A separate baseline reset then preceded the reportable rejection run.

This is not a process failure or product finding. No credentials or API/database process mutation were used.
