<?php

namespace Database\Seeders;

use App\Enum\OperatorActivityType;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Enum\QuotationStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OperatorActivity;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ManagerDashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $manager = User::updateOrCreate(
            ['email' => 'manager@studio.test'],
            ['name' => 'Manager Studio', 'phone' => '6281200000001', 'password' => env('SEED_DEFAULT_PASSWORD', 'password'),
                'role' => 'manager', 'is_active' => true, 'email_verified_at' => $now],
        );
        $client = User::updateOrCreate(
            ['email' => 'klien@studio.test'],
            ['name' => 'Klien Contoh', 'phone' => '6281200000004', 'password' => env('SEED_DEFAULT_PASSWORD', 'password'),
                'role' => 'client', 'is_active' => true, 'email_verified_at' => $now, 'crm_consent_at' => $now],
        );
        $operator = User::updateOrCreate(
            ['email' => 'operator1@studio.test'],
            ['name' => 'Operator Satu', 'phone' => '6281200000002', 'password' => env('SEED_DEFAULT_PASSWORD', 'password'),
                'role' => 'operator', 'is_active' => true, 'email_verified_at' => $now],
        );

        $packages = collect([
            ['name' => 'Paket Tungku Single', 'description' => 'Rekaman, editing, mixing, dan mastering untuk satu track.', 'track_count' => 1, 'recording_hours' => 2, 'auto_price' => 1_500_000],
            ['name' => 'Paket Tungku EP', 'description' => 'Produksi lengkap untuk lima track.', 'track_count' => 5, 'recording_hours' => 8, 'auto_price' => 6_500_000],
            ['name' => 'Paket Mixing Mastering', 'description' => 'Mixing dan mastering untuk demo multitrack.', 'track_count' => 3, 'recording_hours' => 0, 'auto_price' => 2_250_000],
            ['name' => 'DEMO Paket Akustik', 'description' => 'Paket produksi akustik untuk dua lagu, mulai dari rekaman hingga mastering.', 'track_count' => 2, 'recording_hours' => 4, 'auto_price' => 3_100_000, 'stages' => ['recording', 'editing', 'mixing', 'mastering']],
            ['name' => 'DEMO Paket Vokal', 'description' => 'Paket rekaman vokal dan editing ringan untuk satu lagu.', 'track_count' => 1, 'recording_hours' => 3, 'auto_price' => 1_300_000, 'stages' => ['recording', 'editing']],
            ['name' => 'DEMO Paket Podcast', 'description' => 'Pembersihan dan mixing audio untuk episode podcast.', 'track_count' => 2, 'recording_hours' => 0, 'auto_price' => 1_200_000, 'stages' => ['editing', 'mixing']],
            ['name' => 'DEMO Paket Mixing', 'description' => 'Layanan mixing multitrack tanpa sesi rekaman.', 'track_count' => 4, 'recording_hours' => 0, 'auto_price' => 1_600_000, 'stages' => ['mixing']],
            ['name' => 'DEMO Paket Mastering', 'description' => 'Mastering stereo untuk satu single siap distribusi.', 'track_count' => 1, 'recording_hours' => 0, 'auto_price' => 500_000, 'stages' => ['mastering']],
            ['name' => 'DEMO Paket Live Session', 'description' => 'Perekaman live band dengan proses produksi lengkap.', 'track_count' => 3, 'recording_hours' => 6, 'auto_price' => 4_200_000, 'stages' => ['recording', 'editing', 'mixing']],
            ['name' => 'DEMO Paket Demo Band', 'description' => 'Paket demo band: rekaman dan mixing untuk tiga lagu.', 'track_count' => 3, 'recording_hours' => 5, 'auto_price' => 3_700_000, 'stages' => ['recording', 'mixing']],
            ['name' => 'DEMO Paket Instrumen', 'description' => 'Editing dan mastering rekaman instrumen dua lagu.', 'track_count' => 2, 'recording_hours' => 0, 'auto_price' => 900_000, 'stages' => ['editing', 'mastering']],
            ['name' => 'DEMO Paket Album Ringkas', 'description' => 'Paket produksi lengkap untuk delapan lagu.', 'track_count' => 8, 'recording_hours' => 14, 'auto_price' => 9_800_000, 'stages' => ['recording', 'editing', 'mixing', 'mastering']],
            ['name' => 'DEMO Paket Revisi Mix', 'description' => 'Penyempurnaan mixing untuk tiga lagu yang sudah direkam.', 'track_count' => 3, 'recording_hours' => 0, 'auto_price' => 1_050_000, 'stages' => ['mixing', 'mastering']],
            ['name' => 'DEMO Paket Voice Over', 'description' => 'Perekaman dan editing voice over berdurasi pendek.', 'track_count' => 1, 'recording_hours' => 2, 'auto_price' => 800_000, 'stages' => ['recording', 'editing']],
        ])->mapWithKeys(function (array $attributes) use ($manager): array {
            $includedStages = $attributes['stages'] ?? ['editing', 'mixing', 'mastering'];
            $package = Package::withTrashed()->updateOrCreate(
                ['name' => $attributes['name']],
                [
                    'description' => $attributes['description'],
                    'track_count' => $attributes['track_count'],
                    'recording_hours' => in_array('recording', $includedStages, true) ? $attributes['recording_hours'] : 0,
                    'auto_price' => $attributes['auto_price'],
                    'include_recording' => in_array('recording', $includedStages, true),
                    'include_editing' => in_array('editing', $includedStages, true),
                    'include_mixing' => in_array('mixing', $includedStages, true),
                    'include_mastering' => in_array('mastering', $includedStages, true),
                    'is_price_manual' => false, 'manual_price' => null, 'is_active' => true,
                    'created_by' => $manager->id, 'deleted_at' => null],
            );

            return [$attributes['name'] => $package];
        });

        $expenseCategories = collect(['Sewa tempat', 'Listrik dan internet', 'Peralatan'])->mapWithKeys(
            fn (string $name): array => [$name => ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true])],
        );

        foreach ([
            ['name' => 'Project Senja', 'package' => 'Paket Tungku Single', 'status' => ProjectStatus::InProgress, 'days' => 10],
            ['name' => 'Project Langit', 'package' => 'Paket Tungku EP', 'status' => ProjectStatus::AwaitingAssignment, 'days' => 20],
            ['name' => 'Project Hujan', 'package' => 'Paket Tungku EP', 'status' => ProjectStatus::Completed, 'days' => 35],
            ['name' => 'Project Pagi', 'package' => 'Paket Mixing Mastering', 'status' => ProjectStatus::AwaitingFinalPayment, 'days' => 7],
        ] as $index => $definition) {
            $package = $packages[$definition['package']];
            $createdAt = $now->subDays($definition['days']);
            $project = Project::updateOrCreate(
                ['code' => 'DEMO-DASH-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                ['client_id' => $client->id, 'package_id' => $package->id, 'quotation_id' => null,
                    'name' => $definition['name'], 'package_snapshot' => $package->toArray(),
                    'price' => $package->price, 'po_amount' => (int) round($package->price * 0.5),
                    'status' => $definition['status'], 'completed_at' => $definition['status'] === ProjectStatus::Completed ? $createdAt : null,
                    'created_at' => $createdAt, 'updated_at' => $createdAt],
            );

            foreach (StageType::cases() as $stage) {
                $stageStatus = match (true) {
                    $definition['status'] === ProjectStatus::Completed => StageStatus::Completed,
                    $stage === StageType::Recording => StageStatus::Completed,
                    $stage === StageType::Editing => StageStatus::InProgress,
                    default => StageStatus::Locked,
                };
                $projectStage = ProjectStage::updateOrCreate(
                    ['project_id' => $project->id, 'stage' => $stage->value],
                    ['sequence' => $stage->sequence(),
                        'operator_id' => $stage === StageType::Editing ? $operator->id : null,
                        'status' => $stageStatus,
                        'deadline_at' => $stage === StageType::Editing ? $now->addDays(3) : null,
                        'assigned_at' => $stage === StageType::Editing ? $now->subDay() : null,
                        'started_at' => $stage === StageType::Editing ? $now->subHours(5) : null,
                        'completed_at' => $stageStatus === StageStatus::Completed ? $createdAt->addDay() : null],
                );

                if ($stageStatus !== StageStatus::Locked) {
                    $track = $project->tracks()->firstOrCreate(
                        ['position' => 1], ['title' => $definition['name'].' — Track 1'],
                    );
                    $projectStage->trackProgress()->firstOrCreate(
                        ['project_track_id' => $track->id],
                        ['status' => $stageStatus === StageStatus::Completed ? TrackProgressStatus::Approved : TrackProgressStatus::Pending],
                    );
                }
            }

            Payment::updateOrCreate(
                ['order_id' => 'DEMO-DASH-PO-'.($index + 1)],
                ['project_id' => $project->id, 'invoice_id' => null, 'type' => PaymentType::Po,
                    'amount' => $project->po_amount, 'status' => PaymentStatus::Paid,
                    'provider' => PaymentProvider::Cash, 'provider_transaction_id' => 'DEMO-DASH-TXN-PO-'.($index + 1),
                    'payment_method' => 'cash', 'paid_at' => $createdAt->addHours(2),
                    'expires_at' => null, 'raw_payload' => null],
            );

            if ($definition['status'] === ProjectStatus::Completed) {
                Payment::updateOrCreate(
                    ['order_id' => 'DEMO-DASH-FINAL-'.($index + 1)],
                    ['project_id' => $project->id, 'invoice_id' => null, 'type' => PaymentType::Final,
                        'amount' => $project->price - $project->po_amount, 'status' => PaymentStatus::Paid,
                        'provider' => PaymentProvider::Cash, 'provider_transaction_id' => 'DEMO-DASH-TXN-FINAL-'.($index + 1),
                        'payment_method' => 'cash', 'paid_at' => $createdAt->addDays(2),
                        'expires_at' => null, 'raw_payload' => null],
                );
            }

            if ($definition['name'] === 'Project Senja') {
                $room = Room::firstOrCreate(['name' => 'Studio Utama'], ['is_active' => true]);
                RecordingSession::updateOrCreate(
                    ['room_id' => $room->id, 'project_id' => $project->id, 'start_at' => $now->setTime(14, 0)],
                    ['project_stage_id' => $project->stages()->where('stage', StageType::Recording->value)->value('id'),
                        'client_id' => $client->id, 'payment_id' => null, 'package_id' => $package->id,
                        'end_at' => $now->setTime(16, 0), 'status' => SessionStatus::Confirmed,
                        'held_until' => null, 'is_extension' => false, 'extra_fee' => 0,
                        'requested_by' => $client->id],
                );
            }
        }

        foreach ([
            ['name' => 'Rekaman vokal untuk single terbaru', 'status' => QuotationStatus::AwaitingStudio],
            ['name' => 'Produksi mini album', 'status' => QuotationStatus::AwaitingClient],
        ] as $definition) {
            Quotation::updateOrCreate(
                ['client_id' => $client->id, 'name' => $definition['name']],
                ['track_count' => 1, 'recording_hours' => 2, 'include_recording' => true,
                    'include_editing' => true, 'include_mixing' => true, 'include_mastering' => true,
                    'notes' => 'Data demo dashboard.', 'status' => $definition['status'],
                    'auto_price' => 1_500_000, 'agreed_price' => null, 'closed_by' => null, 'closed_at' => null],
            );
        }

        foreach ([
            ['category' => 'Sewa tempat', 'description' => 'Sewa studio bulan ini', 'amount' => 2_500_000, 'days' => 3],
            ['category' => 'Listrik dan internet', 'description' => 'Listrik dan internet studio', 'amount' => 850_000, 'days' => 8],
            ['category' => 'Peralatan', 'description' => 'Kabel audio dan aksesoris', 'amount' => 475_000, 'days' => 12],
        ] as $definition) {
            Expense::updateOrCreate(
                ['description' => '[DEMO DASHBOARD] '.$definition['description']],
                ['expense_category_id' => $expenseCategories[$definition['category']]->id,
                    'recorded_by' => $manager->id, 'amount' => $definition['amount'],
                    'spent_on' => $now->subDays($definition['days'])->toDateString()],
            );
        }

        $this->seedDemoActivities($operator, $now);
        $this->seedHistoricalFinance($manager, $client, $packages['Paket Tungku Single'], $now);
    }

    private function seedDemoActivities(User $operator, CarbonImmutable $now): void
    {
        $project = Project::where('code', 'DEMO-DASH-001')->firstOrFail();
        $stage = ProjectStage::where('project_id', $project->id)->where('stage', StageType::Editing->value)->firstOrFail();
        $track = $project->tracks()->where('position', 1)->firstOrFail();

        foreach ([
            ['type' => OperatorActivityType::DeadlineConfirmed, 'source_id' => 980_001, 'hours' => 10,
                'actor' => $operator, 'actor_name' => $operator->name],
            ['type' => OperatorActivityType::ResultSubmitted, 'source_id' => 980_002, 'hours' => 4,
                'actor' => $operator, 'actor_name' => $operator->name],
            ['type' => OperatorActivityType::StageOverdue, 'source_id' => 980_003, 'hours' => 1,
                'actor' => null, 'actor_name' => null],
        ] as $activity) {
            OperatorActivity::firstOrCreate(
                ['type' => $activity['type'], 'source_id' => $activity['source_id']],
                ['actor_id' => $activity['actor']?->id, 'operator_id' => $operator->id,
                    'project_id' => $project->id, 'project_stage_id' => $stage->id,
                    'project_track_id' => $track->id, 'submission_id' => null,
                    'actor_name' => $activity['actor_name'], 'operator_name' => $operator->name,
                    'project_name' => $project->name, 'track_title' => $track->title,
                    'stage' => StageType::Editing,
                    'deadline_at' => $now->subHours(2),
                    'occurred_at' => $now->subHours($activity['hours']),
                    'recorded_at' => $now->subHours($activity['hours'])->addMinute()],
            );
        }
    }

    private function seedHistoricalFinance(User $manager, User $client, Package $package, CarbonImmutable $now): void
    {
        $year = $now->year - 1;

        foreach ([
            ['month' => 1, 'revenue' => 1_250_000, 'expense' => 320_000],
            ['month' => 3, 'revenue' => 2_500_000, 'expense' => 450_000],
            ['month' => 6, 'revenue' => 1_750_000, 'expense' => 275_000],
            ['month' => 9, 'revenue' => 3_000_000, 'expense' => 600_000],
            ['month' => 12, 'revenue' => 2_250_000, 'expense' => 390_000],
        ] as $index => $month) {
            $date = CarbonImmutable::create($year, $month['month'], 12, 12, 0, 0, config('app.timezone'));
            $code = sprintf('DEMO-ARCH-%d-%02d', $year, $month['month']);
            $project = Project::updateOrCreate(
                ['code' => $code],
                ['client_id' => $client->id, 'package_id' => $package->id, 'quotation_id' => null,
                    'name' => 'Project Arsip '.$date->translatedFormat('F Y'),
                    'package_snapshot' => $package->toArray(), 'price' => $month['revenue'] * 2,
                    'po_amount' => $month['revenue'], 'status' => ProjectStatus::Completed,
                    'completed_at' => $date->addDays(2), 'created_at' => $date, 'updated_at' => $date->addDays(2)],
            );

            Payment::updateOrCreate(
                ['order_id' => 'DEMO-ARCHIVE-PO-'.$year.'-'.$month['month']],
                ['project_id' => $project->id, 'invoice_id' => null, 'type' => PaymentType::Po,
                    'amount' => $month['revenue'], 'status' => PaymentStatus::Paid,
                    'provider' => PaymentProvider::Cash, 'provider_transaction_id' => 'DEMO-ARCHIVE-TXN-'.$year.'-'.$month['month'],
                    'payment_method' => 'cash', 'paid_at' => $date->addHour(), 'expires_at' => null, 'raw_payload' => null],
            );

            Expense::updateOrCreate(
                ['description' => sprintf('[DEMO DASHBOARD] Arsip %d-%02d', $year, $month['month'])],
                ['expense_category_id' => ExpenseCategory::firstOrCreate(['name' => 'Sewa tempat'], ['is_active' => true])->id,
                    'recorded_by' => $manager->id, 'amount' => $month['expense'], 'spent_on' => $date->toDateString()],
            );
        }
    }
}
