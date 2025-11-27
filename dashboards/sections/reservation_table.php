<?php if (!defined('DIRECT_ACCESS')) die('Direct access not permitted'); ?>
<div class="card card-table mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><?= ucfirst($status) ?> Student Booking Requests</h6>
        <p>Review and manage student reservation requests</p>

        <div class="table-responsive">
            <table class="table align-middle" style="min-width:900px;">
                <thead>
                    <tr class="text-muted small">
                        <th>Property</th>
                        <th>Tenant</th>
                        <th style="min-width:220px;">Dates</th>
                        <th style="min-width:160px;">Amount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reservations)): ?>
                        <?php foreach ($reservations as $r):

                            $prop = htmlspecialchars($r['property_title']);
                            $tenant = htmlspecialchars($r['student_name']);
                            $email  = htmlspecialchars($r['student_email']);
                            $phone  = htmlspecialchars($r['student_phone']);

                            $startDate = $r['check_in_date'];
                            $lease     = intval($r['lease_length']);

                            // Prefer the reservation amount (if set and >0), otherwise fall back to the property's rent
                            $amount = (isset($r['amount']) && floatval($r['amount']) > 0)
                                ? $r['amount']
                                : ($r['property_rent'] ?? 0);

                            // Status handling
                            $stat = htmlspecialchars($r['status'] ?? 'pending');

                            $statusColor = 'secondary';
                            if ($stat === 'pending')  $statusColor = 'warning';
                            if ($stat === 'confirmed') $statusColor = 'success';
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

                            <td style="min-width:220px;">

                                <?php
                                    // Lease end calculation
                                    $endDate = (!empty($startDate) && $lease > 0)
                                        ? date('Y-m-d', strtotime("+$lease months", strtotime($startDate)))
                                        : "Not specified";
                                ?>

                                <div style="white-space:nowrap;"><strong>Check-in:</strong>&nbsp;<?= $startDate ?></div>
                                <div style="white-space:nowrap;"><strong>Lease Ends:</strong>&nbsp;<?= $endDate ?></div>
                                <div class="small-muted"><?= $createdAgo ?></div>
                            </td>

                            <td style="min-width:160px; white-space:nowrap;">
                                <div class="fw-semibold">KES&nbsp;<?= number_format((float)$amount, 0) ?></div>
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
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No reservations found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>   
    </div>
</div>
