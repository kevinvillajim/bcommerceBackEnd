<?php

namespace App\Console\Commands;

use App\Domain\Repositories\FeedbackRepositoryInterface;
use App\Models\Admin;
use App\Models\Feedback;
use App\UseCases\Feedback\GenerateDiscountCodeUseCase;
use App\UseCases\Feedback\ReviewFeedbackUseCase;
use Illuminate\Console\Command;

class DebugFeedback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feedback:debug {feedbackId?} {--approve} {--code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug feedback and discount code generation';

    /**
     * Execute the console command.
     */
    public function handle(
        FeedbackRepositoryInterface $feedbackRepository,
        ReviewFeedbackUseCase $reviewFeedbackUseCase,
        GenerateDiscountCodeUseCase $generateDiscountCodeUseCase
    ) {
        $feedbackId = $this->argument('feedbackId');

        if (! $feedbackId) {
            // Listar todos los feedbacks
            $this->listAllFeedbacks();

            return;
        }

        // Mostrar detalles de un feedback específico
        $feedback = $feedbackRepository->findById($feedbackId);

        if (! $feedback) {
            $this->error("Feedback with ID {$feedbackId} not found");

            return;
        }

        $this->info("Feedback #{$feedbackId} Details:");
        $this->table(
            ['ID', 'User ID', 'Title', 'Status', 'Reviewed By', 'Reviewed At'],
            [
                [
                    $feedback->getId(),
                    $feedback->getUserId(),
                    $feedback->getTitle(),
                    $feedback->getStatus(),
                    $feedback->getReviewedBy(),
                    $feedback->getReviewedAt(),
                ],
            ]
        );

        // Aprobar el feedback si se indica
        if ($this->option('approve') && $feedback->getStatus() === 'pending') {
            $admin = Admin::first();

            if (! $admin) {
                $this->error('No admin found in the database. Create an admin first.');

                return;
            }

            try {
                $this->info("Approving feedback #{$feedbackId} with admin ID #{$admin->id}");
                $updatedFeedback = $reviewFeedbackUseCase->approve($feedbackId, $admin->id, 'Approved from debug command');
                $this->info('Feedback approved successfully');

                // Regenerar código si se indica
                if ($this->option('code')) {
                    $this->info("Generating discount code for feedback #{$feedbackId}");
                    $discountCode = $generateDiscountCodeUseCase->execute($feedbackId, 30);
                    $this->info("Discount code generated: {$discountCode->getCode()}");
                    $this->info("Expires at: {$discountCode->getExpiresAt()}");
                }
            } catch (\Exception $e) {
                $this->error('Error: '.$e->getMessage());
                $this->error($e->getTraceAsString());
            }
        }
    }

    /**
     * List all feedbacks in the database
     */
    private function listAllFeedbacks()
    {
        $feedbacks = Feedback::all();

        if ($feedbacks->isEmpty()) {
            $this->info('No feedbacks found in the database');

            return;
        }

        $this->info('All Feedbacks:');
        $data = [];

        foreach ($feedbacks as $feedback) {
            $data[] = [
                $feedback->id,
                $feedback->user_id,
                $feedback->title,
                $feedback->status,
                $feedback->reviewed_by,
                $feedback->reviewed_at,
            ];
        }

        $this->table(['ID', 'User ID', 'Title', 'Status', 'Reviewed By', 'Reviewed At'], $data);
    }
}
