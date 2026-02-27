<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailService;
use App\Services\SurpriseRepository;
use Throwable;

final class SurpriseController
{
    public function all(Request $request): void
    {
        if (!Auth::isLoggedIn()) {
            Response::redirect('/');
        }

        try {
            $repository = new SurpriseRepository();
            Response::json($repository->getAllSorted());
        } catch (Throwable $exception) {
            Response::json(['error' => $exception->getMessage()]);
        }
    }

    public function notify(Request $request): void
    {
        try {
            $repository = new SurpriseRepository();
            $surprises = $repository->getAllSorted();
            $id = (string) $request->route('id', '');

            $surpriseIndex = -1;
            foreach ($surprises as $index => $surprise) {
                if ((string) ($surprise['id'] ?? '') === $id) {
                    $surpriseIndex = $index;
                    break;
                }
            }

            $success = true;
            $message = 'Success';

            if ($surpriseIndex >= 0) {
                $email = new EmailService();
                $email->send(
                    'Your Surprise is Ready!',
                    sprintf("Looks like it's time to see surprise #%d!!!", $surpriseIndex + 1)
                );
            } else {
                $success = false;
                $message = sprintf('There is no surprise with the id of "%s"', $id);
            }

            Response::json([
                'success' => $success,
                'message' => $message,
            ]);
        } catch (Throwable $exception) {
            Response::json(['error' => $exception->getMessage()]);
        }
    }

    public function viewed(Request $request): void
    {
        try {
            $repository = new SurpriseRepository();
            $id = (string) $request->route('id', '');
            $surprise = $repository->markViewed($id);
            if ($surprise === null) {
                Response::json(['error' => 'Surprise not found'], 404);
            }

            Response::json($surprise);
        } catch (Throwable $exception) {
            Response::json(['error' => $exception->getMessage()]);
        }
    }

    public function delete(Request $request): void
    {
        try {
            $repository = new SurpriseRepository();
            $id = (string) $request->route('id', '');
            $surprise = $repository->delete($id);
            if ($surprise === null) {
                Response::json(['error' => 'Surprise not found'], 404);
            }

            Response::json($surprise);
        } catch (Throwable $exception) {
            Response::json(['error' => $exception->getMessage()]);
        }
    }

    public function update(Request $request): void
    {
        try {
            $repository = new SurpriseRepository();
            $id = (string) $request->route('id', '');
            $updated = $repository->update($id, $request->all());
            if ($updated === null) {
                Response::json(['error' => 'Surprise not found'], 404);
            }

            Response::json($updated);
        } catch (Throwable $exception) {
            Response::json(['error' => $exception->getMessage()]);
        }
    }

    public function create(Request $request): void
    {
        try {
            $repository = new SurpriseRepository();
            $newSurprise = $repository->create($request->all());
            $surprises = $repository->getAllSorted();
            $email = new EmailService();
            $emailRes = $email->send(
                'New Surprise!',
                "There's a new surprise waiting for you! See if you can guess it ;)"
            );

            Response::json([
                'surprises' => $surprises,
                'newSurprise' => $newSurprise,
                'emailRes' => $emailRes,
            ]);
        } catch (Throwable $exception) {
            Response::json(['error' => $exception->getMessage()]);
        }
    }

    public function testEmail(Request $request): void
    {
        try {
            if (Env::get('TESTING_PASSWORD') !== (string) $request->input('password', '')) {
                Response::json([
                    'error' => 'Invalid credentials',
                ]);
            }

            $override = null;
            $toEmail = trim((string) $request->input('to_email', ''));
            if ($toEmail !== '' && is_valid_email($toEmail)) {
                $override = $toEmail;
            }

            $email = new EmailService();
            $emailRes = $email->send(
                (string) $request->input('email_subject', 'Test Subject'),
                (string) $request->input('email_body', 'Test Body'),
                $override,
            );

            Response::json(['emailRes' => $emailRes]);
        } catch (Throwable $exception) {
            Response::json(['error' => $exception->getMessage()]);
        }
    }
}
