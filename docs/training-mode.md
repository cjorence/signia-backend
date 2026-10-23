# Training capability deployment

Set `FSL_TRAINING_ENABLED` in Laravel hosting variables and in the matching Python service. Both must be `true` to enable hosted collection/training. Production defaults to `false`; local `.env.example` enables local training. Refresh Laravel's config cache and web processes after a variable change.

Disabled start/upload calls return 409 `training_disabled` before payload validation or upstream work. Admin capabilities, labels, statistics, job status, model downloads and learner recognition retain their existing authorization. The capability response combines Laravel's setting with Python's setting; missing upstream support fails closed in the UI. Deploy matching reviewed changes in all three repositories.

To disable safely, first close admission by disabling Laravel while leaving Python running. Wait for already admitted uploads and jobs to complete, record job IDs, check Python's `training_busy` capability, and back up/restore-test the complete asset set. Only then disable/restart Python. A restart can briefly interrupt recognition and loses process-local state; schedule it outside user sessions. Never clear a volume or regenerate label IDs to change modes.

See the FSL repository's `TRAINING_MODE.md` for the complete drain, manifest verification, local model release, rollback and re-enable procedure. A copied checkpoint alone is insufficient: preserve source sequences, checkpoint metadata, `csv/labels.csv`, encoders and preprocessing assets.

Keep `PAYMONGO_MODE=test`, Python/MySQL on private networking, and all private tokens/keys in hosting variables. No frontend public training override is required or honored.
