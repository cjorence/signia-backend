# Finding production failures

Logs are the server's diagnostic record. Error tracking adds grouping, a searchable dashboard and alerts. Neither automatically records everything a user did, and neither replaces backups or uptime monitoring.

## What is implemented

Each routed API request gets a server-generated `X-Request-ID` response header. A JSON object response with status 500–599 also gets a `request_id` field; its existing message and status remain unchanged. This reference can be read in the browser Network panel and shared with support. The frontend does not yet display it in a dedicated error dialog.

The default Laravel log channel includes that reference in messages written during the API request. Server failures produce an `API request failed` event containing route template, method, status, duration and internal user ID when available. Production API exceptions produce an `API exception` event with the same reference, exception type, source location and a bounded trace without argument values. These two entries describe the same incident; count response events rather than counting every log line as another failure. Successful camera frames and ordinary validation failures are not logged by this middleware.

No body, query string, route parameter values, authorization headers, cookies, camera frames or raw exception messages are added by these diagnostics. Internal user IDs and source locations are still private operational data: limit access to maintainers. Existing explicit log calls, framework/bootstrap failures, CLI/queue workers, mail logs, web-server access logs and hosting logs need their own review; this is not a global sanitizer. Local exceptions retain Laravel's detailed developer diagnostics. Production must use `APP_DEBUG=false`.

## Configure at deployment

For a server with persistent, private storage:

```dotenv
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

Logs are under `storage/logs/laravel-YYYY-MM-DD.log`. Laravel's daily channel rotates files; 14 days is an initial retention choice to review against support/privacy needs. Rotation is not a disk-size quota: monitor disk usage, limit abusive traffic and apply host-side storage limits. Keep this directory outside the public web root and restrict OS access. Do not use `MAIL_MAILER=log` in production: email bodies can contain verification/reset codes.

For containers or ephemeral hosts, use `LOG_CHANNEL=stderr` with `LOG_LEVEL=warning` and the host's private log collection. Configure retention and volume limits there; container-local files can disappear on restart. Hosting has not been selected, so no live collection, alerting or retention configuration has been activated. No credentials or current `.env` values were changed.

## Example support workflow

1. A user reports that checkout failed. Ask for the approximate time, what they were doing and the request reference if available—not their password, token, camera image or an unredacted network export.
2. Search the private server/hosting logs for the reference. Locally on Windows, for example: `Get-ChildItem storage/logs/laravel*.log | Select-String -SimpleMatch '<request reference>'`.
3. Match `API request failed` to `API exception`, if present. Read the route, status, source file/line and sanitized trace; check related provider/host logs where necessary. A handled 503 can have no exception entry.
4. Reproduce in a test environment, fix, run relevant tests, deploy, and check whether the failure recurs. A log locates evidence; it does not always reveal the root cause.

## Launch checks and remaining coverage

- In staging, trigger a controlled failure through a temporary test-only route. Confirm its response reference matches the private logs, no sensitive inputs appear, and the production response hides exception details. Do not publish a permanent crash endpoint. Automated tests cover these behaviors without creating a production test route.
- Verify log persistence/access after restart. Configure the chosen host to alert on sustained API 5xx errors and service outages; tune thresholds to actual traffic. This patch sends no external notifications.
- Browser-only JavaScript crashes, Nuxt SSR failures, network failures before Laravel, queue failures and Python/FSL failures are not covered by this API layer. A managed error tracker can add grouping/alerts and frontend source-map support after hosting/privacy settings are agreed. Keep source maps private and scrub collected URLs, request data, user details and breadcrumbs; leave session replay off unless deliberately reviewed.
- With colleague permission, FSL now has correlated diagnostic logs. Laravel forwards `X-Request-ID`; search the same reference in Python stderr/deploy logs for `recognition_exception`, `prediction_failed` and upload/training events. Background training logs also include the job ID. These helpers do not sanitize every third-party/library log. See the workspace `RAILWAY_DEPLOYMENT_HANDOFF.md` for where to find Railway logs and remaining setup.
- Earlier server exceptions deliberately returned as non-5xx responses do not produce an automatic failure event. The unexpected story-package exception is explicitly logged with the reference while preserving its existing status. Expand explicit reporting of other caught failures when their semantics are reviewed.

References: [Laravel error handling](https://laravel.com/framework/docs/12.x/errors), [OWASP logging guidance](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html).
