# Version 1.21.0 Production Checklist

## Before deployment

- Back up the current application and Production configuration.
- Confirm `config/local.php` remains outside the distributed package.
- No Version 1.21 database migration is required.

## Desktop

- Open and close the Bootstrap Offcanvas Drawer.
- Confirm category order and Current state.
- Confirm User Links remain in the Navbar rather than duplicated in the Drawer.
- Open representative RSS, Productivity, Information, Media, Game, and Account Modals.

## Smartphone

- Scroll the Drawer from top to ACCOUNT without horizontal overflow.
- Confirm touch targets remain comfortable and the Drawer close control works.
- Confirm configured User Links appear under USER LINKS.
- Confirm RSS / Information Widget Catalog accordion chevrons are inset from the right edge.
- Confirm Drawer actions close Offcanvas before opening the target Modal.
- Confirm long Modals remain inside the viewport and can scroll.

## Final

- Confirm Logout still uses POST + CSRF and works normally.
- Hard reload once after deployment to clear checkpoint asset caches.
- Record the deployed tag as `v1.21.0`.
