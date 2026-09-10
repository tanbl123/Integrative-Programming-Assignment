<?php
/**
 * HomeController - dashboard and error pages.
 * Module : Shared core - EcoCampus Waste Management System
 */
class HomeController extends Controller
{
    public function index(): void
    {
        try {
            $user = Auth::requireLogin();
            $db = Database::getInstance();

            $data = [
                'title' => 'EcoCampus Waste Management System',
                'user' => $user,
                'dashboardRole' => $user->getRole(),
            ];

            if ($user->isAdmin()) {
                $data += [
                    'binCount' => Bin::count('is_active', 1),
                    'userCount' => User::visibleCount(),
                    'fullBins' => Bin::findFullBins(),
                ];
            } elseif ($user->isCleaner()) {
                $assignedRow = $db->selectOne(
                    "SELECT COUNT(*) AS total
                     FROM collection_assignments ca
                     INNER JOIN collection_schedules cs
                        ON ca.schedule_id = cs.schedule_id
                     WHERE ca.cleaner_id = ?
                     AND ca.assignment_status = 'Assigned'
                     AND cs.schedule_status <> 'Cancelled'
                     AND cs.deleted_at IS NULL",
                    [$user->getKey()]
                );

                $completedRow = $db->selectOne(
                    "SELECT COUNT(*) AS total
                     FROM collection_assignments ca
                     INNER JOIN collection_schedules cs
                        ON ca.schedule_id = cs.schedule_id
                     WHERE ca.cleaner_id = ?
                     AND ca.assignment_status = 'Completed'
                     AND cs.schedule_status <> 'Cancelled'
                     AND cs.deleted_at IS NULL",
                    [$user->getKey()]
                );

                $todayRow = $db->selectOne(
                    "SELECT COUNT(*) AS total
                     FROM collection_assignments ca
                     INNER JOIN collection_schedules cs
                        ON ca.schedule_id = cs.schedule_id
                     WHERE ca.cleaner_id = ?
                     AND ca.assignment_status = 'Assigned'
                     AND cs.schedule_date = CURDATE()
                     AND cs.schedule_status <> 'Cancelled'
                     AND cs.deleted_at IS NULL",
                    [$user->getKey()]
                );

                $assignments = $db->selectAll(
                    "SELECT
                        ca.assignment_id,
                        ca.reason,
                        ca.assignment_status,
                        cs.schedule_date,
                        cs.time_slot,
                        b.bin_id,
                        b.bin_code,
                        b.fill_status,
                        l.location_name,
                        l.building_name,
                        l.floor_no
                     FROM collection_assignments ca
                     INNER JOIN collection_schedules cs
                        ON ca.schedule_id = cs.schedule_id
                     INNER JOIN bins b
                        ON ca.bin_id = b.bin_id
                     LEFT JOIN locations l
                        ON b.location_id = l.location_id
                     WHERE ca.cleaner_id = ?
                     AND ca.assignment_status = 'Assigned'
                     AND cs.schedule_status <> 'Cancelled'
                     AND cs.deleted_at IS NULL
                     ORDER BY cs.schedule_date ASC, cs.time_slot ASC, ca.assignment_id ASC
                     LIMIT 5",
                    [$user->getKey()]
                );

                $data += [
                    'assignedCount' => (int) ($assignedRow['total'] ?? 0),
                    'completedCount' => (int) ($completedRow['total'] ?? 0),
                    'todayCount' => (int) ($todayRow['total'] ?? 0),
                    'cleanerAssignments' => $assignments,
                    'fullBins' => Bin::findFullBins(),
                ];
            } else {
                $complaintRow = $db->selectOne(
                    "SELECT COUNT(*) AS total
                     FROM complaints
                     WHERE reporter_id = ?
                     AND deleted_at IS NULL",
                    [$user->getKey()]
                );

                $pendingRow = $db->selectOne(
                    "SELECT COUNT(*) AS total
                     FROM complaints
                     WHERE reporter_id = ?
                     AND complaint_status IN ('New', 'Assigned')
                     AND deleted_at IS NULL",
                    [$user->getKey()]
                );

                $resolvedRow = $db->selectOne(
                    "SELECT COUNT(*) AS total
                     FROM complaints
                     WHERE reporter_id = ?
                     AND complaint_status = 'Resolved'
                     AND deleted_at IS NULL",
                    [$user->getKey()]
                );

                $data += [
                    'myComplaintCount' => (int) ($complaintRow['total'] ?? 0),
                    'pendingComplaintCount' => (int) ($pendingRow['total'] ?? 0),
                    'resolvedComplaintCount' => (int) ($resolvedRow['total'] ?? 0),
                ];
            }

            $this->view('home/index', $data);
        } catch (AuthenticationException $error) {
            $this->handleAccessFailure($error);
        }
    }

    public function notFound(): void
    {
        http_response_code(404);
        $this->view('home/404', ['title' => 'Page Not Found']);
    }
}