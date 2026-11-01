# Publishing immutable releases — Maintainer checklist

**Audience:** Boilerplate CMS maintainers, not ordinary website administrators. **Status:** release immutability enabled on the official GitHub repository on 1 November 2026; applies to **newly published releases only**. Do not republish, rename, or change the already published `v2.5.1-beta.6` asset. This workflow adds no new CMS code, database migration or update UI.

## Mandatory order

1. **Prepare code and ZIP.** Complete the intended Git commit and push to `main`. Build an installable ZIP rooted at `boilerplate/`, named exactly `Boilerplate-CMS-v<VERSION>.zip`. Verify PHP syntax, QA tests, manifest and migrations; make sure the version in `public_html/incl/init.php` matches the release tag. Exclude `.env`, `storage/`, tests and uploads. The public ZIP must match the reviewed source files byte-for-byte, except expected packaging-only metadata if explicitly documented. Do not use a GitHub-generated *Source code (zip)* as the installable asset.
2. **Create a Draft on GitHub.** Select the exact intended source commit, set Beta/RC as **Pre-release**, upload exactly one distribution ZIP and keep the release as a **Draft**. The official CMS update lookup must not see a draft.
3. **Verify before publishing.** Check the tag and commit; the Draft flag; prerelease classification; exactly one asset with the correct name, uploaded state, file size and GitHub `sha256:` digest matching the local ZIP. If the checks fail, correct the *draft*, not a published release.
4. **Publish once.** Only after all checks pass, publish the Draft. New releases then have an immutable tag and immutable asset. A replacement package needs a **new version/tag/release**, never an overwritten published asset.
5. **Post-publication verification.** Verify attestation for the new release and, where available, the exact local ZIP against that release. Record the version, commit and SHA-256. For a Beta release, perform the usual controlled One-Click Update QA on an isolated or local test installation before claiming it is fully verified.

## GitHub CLI reference — for a **future** release only

**Never run this against Beta 6.** Prepare a reviewed new release, set the new `TAG` and `ZIP`, and run inside the clean Git repository **after pushing `main`**. The commands are split into three stages intentionally; none of the first two stages publishes a release.

**Stage A — create an unpublished Draft with the complete asset:**

```bash
set -e
PROJECT="joachimlindhadberg/boilerplate"
TAG="vX.Y.Z-beta.N"                # Replace with the approved new tag
ZIP="/absolute/path/Boilerplate-CMS-vX.Y.Z-beta.N.zip"
COMMIT="$(git rev-parse HEAD)"

# Check local release basics before contacting GitHub.
test -z "$(git status --porcelain)"
test "$(git rev-parse origin/main)" = "$COMMIT"
test -f "$ZIP"
test "$(basename "$ZIP")" = "Boilerplate-CMS-$TAG.zip"
unzip -tqq "$ZIP"

gh release create "$TAG" "$ZIP" --repo "$PROJECT" \
  --target "$COMMIT" --title "Boilerplate CMS $TAG" \
  --notes "Approved release notes go here." --prerelease --draft
```

**Stage B — verify the Draft; stop if anything differs:**

```bash
set -e
SHA256="$(shasum -a 256 "$ZIP" | awk '{print $1}')"
SIZE="$(wc -c < "$ZIP" | tr -d ' ')"
DRAFT="$(gh release view "$TAG" --repo "$PROJECT" --json isDraft --jq '.isDraft')"
PRE="$(gh release view "$TAG" --repo "$PROJECT" --json isPrerelease --jq '.isPrerelease')"
COUNT="$(gh api "repos/$PROJECT/releases/tags/$TAG" --jq '.assets | length')"
NAME="$(gh api "repos/$PROJECT/releases/tags/$TAG" --jq '.assets[0].name')"
STATE="$(gh api "repos/$PROJECT/releases/tags/$TAG" --jq '.assets[0].state')"
REMOTE_SIZE="$(gh api "repos/$PROJECT/releases/tags/$TAG" --jq '.assets[0].size')"
DIGEST="$(gh api "repos/$PROJECT/releases/tags/$TAG" --jq '.assets[0].digest')"
TAG_COMMIT="$(git ls-remote origin "refs/tags/$TAG" | awk '{print $1}')"

test "$DRAFT" = true
test "$PRE" = true
[ "$COUNT" -eq 1 ]
test "$NAME" = "$(basename "$ZIP")"
test "$STATE" = uploaded
test "$REMOTE_SIZE" = "$SIZE"
test "$DIGEST" = "sha256:$SHA256"
test "$TAG_COMMIT" = "$COMMIT"
printf 'PASS: Draft release, tag, asset and SHA-256 verified.\n'
```

Only when Stage B finishes successfully **and you explicitly approve publishing**, run this **separate** command. Publishing makes the release immutable:

```bash
gh release edit "$TAG" --repo "$PROJECT" --draft=false
```

**Stage C — post-publication verification, using a current GitHub CLI:**

```bash
gh release view "$TAG" --repo "$PROJECT" --json tagName,isDraft,isImmutable,assets
gh release verify "$TAG" --repo "$PROJECT"
gh release verify-asset "$TAG" "$ZIP" --repo "$PROJECT"
```

**Important:** Run stages in the same shell so `TAG`, `ZIP` and `PROJECT` are retained; if you open a new Terminal session, set them again. The release attestation is created on publication, not while the release is still a Draft. The archive's SHA-256 and GitHub release metadata share the publisher's GitHub trust boundary; these checks do not independently authenticate a compromised GitHub publisher account.

## Scope and limits

- GitHub's immutable release attestation binds published assets, tag and commit; it does not prove the committed code is safe, that the GitHub account was never compromised, or that the installed CMS cryptographically validates that attestation.
- Installed Boilerplate versions still validate trusted GitHub metadata, SHA-256, ZIP format, version and migrations. **One-Click Update still refuses new or changed DB migration sources.**
- Immutability does **not** retroactively protect `v2.5.1-beta.6` or earlier releases.
- Never upload a new ZIP to an already published immutable release. For a mistake, cut a new version after fixing the problem and performing QA again.
- This document must remain developer/publisher-facing; ordinary CMS users should use **Tools → Updates** and should not need Terminal, GitHub credentials or manual ZIP uploads.

Official reference: https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases
