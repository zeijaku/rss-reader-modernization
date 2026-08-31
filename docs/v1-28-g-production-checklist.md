# V1.28-G production verification checklist

V1.28-G is the final integration checkpoint before formal V1.28-H publication.

1. Back up application code, private configuration, database, and `var/uploads/`.
2. Deploy the V1.28-G cumulative update without overwriting private configuration/runtime data.
3. Confirm the footer/login application version reports `RSS Reader Modernization 1.28.0-dev.6`.
4. Confirm File Library and RSS Management headings no longer show development phase badges.
5. Confirm File Library upload guidance includes ZIP and the configured 10 MiB default.
6. Verify Image Viewer, File Detail, PDF Viewer, TXT Preview, CSV Preview, Download, Delete, and upload/drag-drop behavior.
7. On Smartphone width, verify four-action cards use a touch-friendly 2x2 layout and TXT/CSV/PDF/Detail Modals remain usable.
8. Verify a different authenticated user cannot access another user's file id.
9. Verify ZIP remains download-only and no archive extraction/browser action appears.
10. Verify invalid/non-UTF-8 TXT/CSV preview fails safely while download remains available.
11. If production confirmation succeeds, perform V1.28-H separately to finalize `1.28.0`, main/tag/GitHub Release.

No V1.28 SQL/Migration, new required secret, or permission change is expected.
