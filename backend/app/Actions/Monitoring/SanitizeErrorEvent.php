<?php

namespace App\Actions\Monitoring;

use App\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventType;
use Sentry\Stacktrace;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SanitizeErrorEvent
{
    public static function handle(Event $event, ?EventHint $hint = null): ?Event
    {
        $exception = $hint?->exception;
        if ($event->getType() !== EventType::event()
            || $exception instanceof ValidationException
            || $exception instanceof AuthorizationException
            || $exception instanceof AuthenticationException
            || $exception instanceof InvalidWebhookSignatureException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500)) {
            return null;
        }

        /** Rebuild from an allowlist so new SDK contexts cannot introduce business payloads. */
        $safe = Event::createEvent($event->getId());
        $safe->setTimestamp($event->getTimestamp())
            ->setLevel($event->getLevel())
            ->setRelease($event->getRelease())
            ->setEnvironment($event->getEnvironment())
            ->setMessage('Application error (details retained in local logs)');

        $exceptions = [];
        foreach ($event->getExceptions() as $original) {
            $copy = clone $original;
            $copy->setValue('[redacted]')->setMechanism(null);
            if ($original->getStacktrace() !== null) {
                $copy->setStacktrace(self::sanitizeStacktrace($original->getStacktrace()));
            }
            $exceptions[] = $copy;
        }
        $safe->setExceptions($exceptions);
        if ($event->getStacktrace() !== null) {
            $safe->setStacktrace(self::sanitizeStacktrace($event->getStacktrace()));
        }

        return $safe;
    }

    private static function sanitizeStacktrace(Stacktrace $stacktrace): Stacktrace
    {
        $frames = [];
        foreach ($stacktrace->getFrames() as $original) {
            $frame = clone $original;
            $frame->setVars([])->setPreContext([])->setPostContext([])->setContextLine(null);
            $frames[] = $frame;
        }

        return new Stacktrace($frames);
    }
}
