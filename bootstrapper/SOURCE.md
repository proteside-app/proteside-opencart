# Bundled Proteside bootstrapper

| File | Purpose |
| --- | --- |
| `bootstrapper.min.js` | The inline, synchronous bootstrapper emitted by the extension (byte-for-byte; the trailing newline is part of the CSP hash below). |
| `bootstrapper.src.index.ts` | Readable source of the bootstrapper. |
| `bootstrapper.src.match.ts` | Readable source of the rule matcher used by the bootstrapper. |
| `VERSION` | Bootstrapper version (`1.1.3`, matches `proteside-sdk` 1.1.3). |

Origin: copied unchanged from the Proteside SDK repository (`proteside-sdk` 1.1.3: build output
`dist/bootstrapper.min.js` and sources `src/bootstrapper/index.ts`, `src/bootstrapper/match.ts`). It is **never**
downloaded from the CDN at runtime: it ships with the extension and is updated together with the extension version.

sha256 of `bootstrapper.min.js` 1.1.3, which is the CSP `script-src` hash when no inline rules are embedded:
`sha256-t2ypUgwBPt6N82k2gXq5D5ZHIdxOXxhj6ZISJatVbg8=`

To update: replace the four files with the new ones from the `proteside-sdk` build, bump `ProtesideService::VERSION`
(`lib/service.php`) and `install.json`, update `CHANGELOG.md` and the hash in `tests/snippet_test.php`, then run
`php tools/build.php` and the tests.
