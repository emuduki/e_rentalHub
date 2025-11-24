<?php if (!defined('DIRECT_ACCESS')) die('Direct access not permitted'); ?>
<div class="card card-table mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><?= ucfirst($status) ?> Student Booking Requests</h6>
        <p>Review and manage student reservation requests</p>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="text-muted small">
                        <th>Property</th>
                        <th>Tenant</th>
                        <th>Dates</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reservations)): ?>
                        <?php foreach ($reservations as $r):

                            // Safe defaults
                            $prop = htmlspecialchars($r['title'] ?? 'Property');

                            // Student details from users table
                            $tenant = htmlspecialchars($r['name'] ?? 'Student');
                            $email  = htmlspecialchars($r['email'] ?? '');
                            $phone  = htmlspecialchars($r['phone_number'] ?? '');

                            // Reservation dates
                            $startDate = $r['check_in_date'] ?? null;
                            $lease     = intval($r['lease_length'] ?? 0);

                            $start = $startDate ? date('d/m/Y', strtotime($startDate)) : '-';
                            $end   = ($startDate && $lease > 0)
                                ? date('d/m/Y', strtotime("+{$lease} months", strtotime($startDate)))
                                : '-';

                            // Amount (from property rent)
                            $amount = $r['rent'] ?? 0;

                            // Status handling
                            $stat = htmlspecialchars($r['status'] ?? 'pending');

                            $statusColor = 'secondary';
                            if ($stat === 'pending')  $statusColor = 'warning';
                            if ($stat === 'approved') $statusColor = 'success';
                            if ($stat === 'rejected') $statusColor = 'danger';
                            if ($stat === 'completed') $statusColor = 'dark';

                            $createdAgo = time_ago($r['created_at'] ?? null);
                        ?>

                        <tr>
                            <td style="min-width: 220px;">
                                <div class="fw-semibold"><?= $prop ?></div>
                                <div class="small-muted">#<?= intval($r['id']) ?></div>
                            </td>

                            <td style="min-width: 200px;">
                                <div class="fw-semibold"><?= $tenant ?></div>
                                <div class="small-muted"><?= $email ?> <?= $phone ? " · $phone" : "" ?></div>
                            </td>

                            <td style="min-width: 180px;">
                                <div><?= $start ?> <span class="small-muted">to</span> <?= $end ?></div>
                                <div class="small-muted"><?= $createdAgo ?></div>
                            </td>

                            <td>
                                <div class="fw-semibold">KES <?= number_format((float)$amount, 0) ?></div>
                            </td>

                            <td>
                                <div class="status-pill bg-<?= $statusColor ?> text-white"><?= $stat ?></div>
                            </td>

                            <td class="text-end" style="min-width: 230px;">
                                <a href="#" class="btn btn-outline-secondary me-2" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>

                                <form class="action-form" method="POST" style="display:inline">
                                    <input type="hidden" name="reservation_id" value="<?= intval($r['id']) ?>">
                                    <input type="hidden" name="reservation_action" value="approve">
                                    <button type="submit" class="btn btn-approve me-2">
                                        <i class="bi bi-check-lg me-1"></i>Approve
                                    </button>
                                </form>

                                <form class="action-form" method="POST" style="display:inline">
                                    <input type="hidden" name="reservation_id" value="<?= intval($r['id']) ?>">
                                    <input type="hidden" name="reservation_action" value="reject">
                                    <button type="submit" class="btn btn-reject">
                                        <i class="bi bi-x-lg me-1"></i>Reject
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>   
    </div>
</div>
