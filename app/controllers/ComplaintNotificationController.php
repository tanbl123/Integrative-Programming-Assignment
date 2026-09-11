<?php
/**
 * The notices raised by the complaint observers, shown to the people they
 * were raised for.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management - Observer design pattern
 *
 * ComplaintNotificationObserver has written a row for every complaint event
 * since the module was built. Nothing read them: complaint_notifications was
 * written and never displayed, so one of the four observers produced output
 * no user ever saw. This controller is the missing half.
 *
 * Who sees what is not decided here. It is asked of the signed-in user, the
 * same way the complaint list asks - a Reporter answers with notices about
 * their own reports, an Administrator with the shared administrator queue,
 * and a Cleaner with none.
 */
class ComplaintNotificationController extends Controller
{
    /**
     * The notification panel. Asks the user object which notices are theirs, so a
     * Reporter and an Administrator get different queues from the same page.
     */
    public function index(): void
    {
        try {
            $user = Auth::requireLogin();

            $this->view('complaint/notifications', [
                'title'         => 'Notifications',
                'user'          => $user,
                'notifications' => $user->visibleNotifications(),
                'unreadCount'   => $user->unreadNotificationCount(),
            ]);
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /** Marks one notice read, if it is one of this user's own. */
    public function read(int $id): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $user = Auth::requireLogin();

            $notification = ComplaintNotification::find($id);
            if ($notification === null || !$user->maySeeNotification($notification)) {
                // Not found rather than forbidden: a reporter probing ids
                // should not learn that somebody else's notice exists.
                $this->entityNotFound('Notification');
                return;
            }

            ComplaintNotification::markRead([$id]);
            $this->redirect('complaint-notification');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    /**
     * Marks everything this user can see read.
     *
     * The ids come from what the user is allowed to see, not from the form,
     * so a posted id cannot reach a notice that is not theirs.
     */
    public function readAll(): void
    {
        $this->requirePost();
        try {
            Csrf::requireValid($_POST['_token'] ?? null);
            $user = Auth::requireLogin();

            $unread = $user->visibleNotifications(true);
            ComplaintNotification::markRead(
                array_map(static fn(ComplaintNotification $n): int => (int) $n->getKey(), $unread)
            );

            Flash::set('success', $unread === []
                ? 'There was nothing unread.'
                : count($unread) . ' notification' . (count($unread) === 1 ? '' : 's') . ' marked as read.');
            $this->redirect('complaint-notification');
        } catch (AuthenticationException|AuthorizationException $error) {
            $this->handleAccessFailure($error);
        }
    }
}
