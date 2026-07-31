# SB-00–02 Revision 2 hotfix

This revision addresses a deployment defect found after the first SB-00–02 package was tested on the target server.

- Added private `config/local.php` support for shared hosting where environment variables are inconvenient or unavailable.
- Added `config/local.php.example`; real `config/local.php` remains ignored by Git and outside `public/`.
- Added non-secret runtime configuration status helper.
- Added private `var/log/error.log` routing when writable and public error reference IDs.
- Added CLI-only runtime health check.
- Relaxed legacy DB function boundary scalar types where HTTP form values arrive as strings under `strict_types=1`; values are still normalized to integers before binding.
- Kept browser error disclosure disabled in production.
