## Comment System Overview

S2 includes a flexible and secure comment system designed to handle validation, spam detection, and moderation.

### Comment Submission Flow

1. **Request Handling**
   Incoming comments are sent to the `CommentController`. This controller performs initial validation of the comment content.

2. **Preview Mode**
   If the comment is submitted with the preview flag, it is formatted and returned without being stored or processed further.

3. **Spam Detection**
   If the comment is valid, it is passed to the `SpamDetectorInterface`. S2 includes a single implementation of this interface, which uses the Akismet service for spam detection. A `SpamDecisionProvider` wraps the detector and applies local heuristics.

   `SpamDetectorInterface` may return one of the following statuses:
    - `disabled`: The spam detection service is not enabled (API key not configured in the [control panel](https://github.com/parpalak/s2/wiki/Control-Panel#configuration)).
    - `failed`: An error occurred while calling the service.
    - `ham`: The comment is classified as not spam.
    - `spam`: The comment is considered spam.
    - `blatant`: The comment is blatant spam and can be safely ignored.

   The decision logic combines the detector status and local heuristics:
   - If the comment is not marked as `ham` and contains links, an error about links is returned.
   - If the comment is marked as `blatant`, an error about spam is returned.
   - Even when Akismet returns `ham`, comments that contain links or HTML tags are sent to manual moderation (not auto-published).

4. **Moderation Logic**
   The engine supports optional manual moderation, controlled via the `S2_PREMODERATION` parameter,
   which can be enabled in the [control panel](https://github.com/parpalak/s2/wiki/Control-Panel#configuration).

   The moderation decision is made by the `SpamDecision`:

    - If the comment is `ham` and contains no links or HTML tags, it is published immediately.
    - Comments classified as `spam` or `blatant` are never published directly.
    - Comments marked as `ham` but containing links or HTML tags are always sent for manual review.
    - If moderation is **enabled**, comments with spam check statuses `disabled` or `failed`
      are sent for manual review.

### Comment Publishing

If the decision is to publish the comment, the following actions are performed in `CommentController`:
- The comment is stored and visible on the page.
- Notification emails are sent to moderators and to users who subscribed to comment replies.
- The user is redirected to the commented page.

### Manual Moderation Flow

If the comment requires manual moderation:
- Notification emails are sent only to moderators whose email does not match the commenter's email.
- The user is redirected to a special controller `CommentSentController`.

The `CommentSentController` can access a special admin cookie (with the `_c` postfix),
which is set upon admin login.
If the logged-in admin’s email matches the commenter's email,
the system assumes the comment was submitted by that moderator:

- The comment is published immediately.
- Subscribers are notified.
- The user is redirected to the post.

If the emails do not match:
- The comment is queued for review.
- Moderators can publish it later via the admin panel.
