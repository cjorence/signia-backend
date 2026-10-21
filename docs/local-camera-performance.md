# Local camera performance

Camera frames go through the authenticated Laravel API before reaching Python.
Static lessons still require three consecutive exact matches, each with a detected
hand and at least 90% confidence. Three frames means three completed predictions,
not three frames from the webcam's video feed.

On Windows, run the API with the project-local launcher:

```sh
# In signia-backend, stop the existing php artisan serve with Ctrl+C first.
./dev-local.cmd
```

Keep Python running as usual. In signia-frontend, continue using
`./npm-local.cmd run dev`. Refresh the lesson after switching the backend server.

The backend launcher uses your installed PHP and its existing OPcache extension.
It changes settings only for this server process; it does not edit php.ini,
upgrade PHP, or change another project's settings. Updated PHP files are checked
on every request. Restart the launcher after changing .env. To undo its use,
stop it and run `php artisan serve` again. This launcher is for local development,
not Railway production.

Measured on this workstation on 2026-10-21: warm `/up` requests on the original
server took 311–335 ms, versus 62–75 ms with this OPcache configuration. Anonymous
prediction requests still returned 401 with both configurations. Blank synthetic
frames sent from Laravel's HTTP client to the running Python service took 35–41 ms
after warm-up. These are component measurements, not an end-to-end benchmark of
a real hand sign; model inference, other requests, and cold starts can add delay.

If the camera is still slow, open browser developer tools → Network, filter for
`recognition/predict`, and check the duration and HTTP status while holding a sign.
200 with long durations points to request/inference latency. Repeated 503 means
unavailable or busy recognition; 429 means throttling. Fast 200 responses with
progress resetting means the confidence, hand-detection, or exact-label condition
was not met. Do not lower the matching threshold to disguise a latency problem.
Do not share request bodies or Authorization headers: these contain camera images
and login credentials. For a failed request, use X-Request-ID to correlate logs.

PHP configuration reference: https://www.php.net/manual/en/opcache.configuration.php
