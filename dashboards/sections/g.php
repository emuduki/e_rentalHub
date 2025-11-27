<style>
        body {
            background-color: #f8f9fa;
            padding-top: 56px; /* match your navbar height */
        }

        .properties-container {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .page-header {
            margin-bottom: 1.5rem;
        }
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .section-heading {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
        }

        .properties-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .properties-table thead {
            background-color: #f9fafb;
        }

        .properties-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
        }

        .properties-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .properties-table tbody tr {
            transition: background-color 0.2s;
        }

        .properties-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .property-info {
            display: flex;
            flex-direction: column;
        }

        .property-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .property-id {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .location-info {
            color: #1f2937;
            font-size: 0.95rem;
        }

        .details-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-item i {
            color: #2563eb;
        }

        .price-info {
            font-weight: 600;
            color: #1f2937;
            font-size: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-available {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-reserved {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-unavailable {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .period-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .period-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .period-item i {
            color: #2563eb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
    </style>

    <div class="position-relative">
				<?php if ($avatarUrl): ?>
					<img src="<?= htmlspecialchars($avatarUrl) ?>" class="avatar" alt="Avatar">
				<?php else: ?>
					<div class="avatar"><?= strtoupper(substr($profile['full_name'] ?: 'KK', 0, 2)) ?></div>
				<?php endif; ?>
				<label class="cam" onclick="document.getElementById('avatar_input').click()"><i class="bi bi-camera"></i></label>
				<input type="file" id="avatar_input" name="avatar" accept="image/*" class="d-none">
			</div>
			<div class="flex-grow-1">
				<h4 class="mb-1"><input type="text" name="full_name" class="form-control form-control-sm" value="<?= htmlspecialchars($profile['full_name'] ?: '') ?>"></h4>
				<div class="text-muted"><input type="text" name="course" class="form-control form-control-sm" value="<?= htmlspecialchars($profile['course'] ?: '') ?>" placeholder="Course / Program" style="max-width:320px"></div>
				<small class="text-muted">Student ID: <input type="text" name="student_identifier" class="form-control form-control-sm d-inline-block" value="<?= htmlspecialchars($profile['student_identifier'] ?: '') ?>" style="width:160px"></small>
			</div>