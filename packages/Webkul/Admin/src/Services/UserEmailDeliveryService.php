<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;
use Webkul\Admin\Models\UserEmailAccount;
use Webkul\Admin\Models\UserEmailAttachment;
use Webkul\Admin\Models\UserEmailMessage;

class UserEmailDeliveryService
{
    public function __construct(
        protected UserEmailSendService $addressHelper,
        protected UserEmailAttachmentService $attachments
    ) {
    }

    public function saveDraft(
        UserEmailAccount $account,
        string $toInput,
        string $ccInput,
        string $subject,
        string $body,
        array $uploads = [],
        ?UserEmailMessage $draft = null,
        ?UserEmailMessage $replyTo = null
    ): UserEmailMessage {
        $to =
            $this->addressHelper
                ->parseAddressInput(
                    $toInput
                );

        $cc =
            $this->addressHelper
                ->parseAddressInput(
                    $ccInput
                );

        if (
            $draft
            && (
                (int) $draft->user_id
                    !== (int) $account->user_id
                || $draft->folder
                    !== 'DRAFT'
            )
        ) {
            throw new RuntimeException(
                'Draft tidak valid.'
            );
        }

        $message =
            $draft
            ?: new UserEmailMessage();

        if (! $message->exists) {
            $message->user_id =
                $account->user_id;

            $message->account_id =
                $account->id;

            $message->folder =
                'DRAFT';

            $message->imap_uid =
                $this->nextLocalUid(
                    $account->id,
                    'DRAFT'
                );

            $message->direction =
                'outgoing';
        }

        $message->from_email =
            $account->email_address;

        $message->to_emails =
            $to
                ? json_encode(
                    $to,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null;

        $message->cc_emails =
            $cc
                ? json_encode(
                    $cc,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null;

        $message->subject =
            trim($subject);

        $message->text_body =
            $body;

        $message->html_body =
            nl2br(
                e($body)
            );

        $message->delivery_status =
            'draft';

        $message->delivery_error =
            null;

        $message->reply_to_message_id =
            $replyTo?->id;

        $message->in_reply_to =
            $replyTo?->message_id;

        $message->save();

        if ($uploads) {
            $this->attachments
                ->storeUploads(
                    $message,
                    $uploads
                );
        }

        return $message;
    }

    public function send(
        UserEmailAccount $account,
        string $toInput,
        string $ccInput,
        string $subject,
        string $body,
        array $uploads = [],
        ?UserEmailMessage $draft = null,
        ?UserEmailMessage $replyTo = null
    ): UserEmailMessage {
        $to =
            $this->addressHelper
                ->parseAddressInput(
                    $toInput
                );

        $cc =
            $this->addressHelper
                ->parseAddressInput(
                    $ccInput
                );

        if (empty($to)) {
            throw new RuntimeException(
                'Minimal satu alamat To diperlukan.'
            );
        }

        if (
            $draft
            && (
                (int) $draft->user_id
                    !== (int) $account->user_id
                || $draft->folder
                    !== 'DRAFT'
            )
        ) {
            throw new RuntimeException(
                'Draft tidak valid.'
            );
        }

        $message =
            $draft
            ?: new UserEmailMessage();

        if (! $message->exists) {
            $message->user_id =
                $account->user_id;

            $message->account_id =
                $account->id;

            $message->imap_uid =
                $this->nextLocalUid(
                    $account->id,
                    'OUTBOX'
                );

            $message->direction =
                'outgoing';
        } else {
            /*
             * MY_EMAIL_FOLDER_UID_TRANSITION_FIX_V1
             *
             * Draft already owns an imap_uid in the DRAFT namespace.
             * When it moves to OUTBOX, give the existing local message a
             * deterministic UID based on its immutable database id.
             * This avoids carrying a DRAFT UID into another folder namespace.
             */
            $message->imap_uid =
                (int) $message->id;
        }

        $message->folder =
            'OUTBOX';

        $message->from_email =
            $account->email_address;

        $message->to_emails =
            json_encode(
                $to,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

        $message->cc_emails =
            $cc
                ? json_encode(
                    $cc,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null;

        $message->subject =
            trim($subject);

        $message->text_body =
            $body;

        $message->html_body =
            nl2br(
                e($body)
            );

        $message->delivery_status =
            'sending';

        $message->delivery_error =
            null;

        $message->failed_at =
            null;

        $message->reply_to_message_id =
            $replyTo?->id;

        $message->in_reply_to =
            $replyTo?->message_id;

        $message->save();

        if ($uploads) {
            $this->attachments
                ->storeUploads(
                    $message,
                    $uploads
                );
        }

        return $this->deliverExisting(
            $account,
            $message,
            $replyTo
        );
    }

    public function retry(
        UserEmailAccount $account,
        UserEmailMessage $message
    ): UserEmailMessage {
        if (
            (int) $message->user_id
            !== (int) $account->user_id
        ) {
            throw new RuntimeException(
                'Outbox message bukan milik user login.'
            );
        }

        if (
            $message->folder !== 'OUTBOX'
            || $message->direction !== 'outgoing'
        ) {
            throw new RuntimeException(
                'Hanya Outbox outgoing yang dapat di-retry.'
            );
        }

        $replyTo = null;

        if ($message->reply_to_message_id) {
            $replyTo =
                UserEmailMessage::query()
                    ->where(
                        'user_id',
                        $account->user_id
                    )
                    ->find(
                        $message->reply_to_message_id
                    );
        }

        return $this->deliverExisting(
            $account,
            $message,
            $replyTo
        );
    }

    private function deliverExisting(
        UserEmailAccount $account,
        UserEmailMessage $message,
        ?UserEmailMessage $replyTo = null
    ): UserEmailMessage {
        $to =
            $this->decodeAddresses(
                $message->to_emails
            );

        $cc =
            $this->decodeAddresses(
                $message->cc_emails
            );

        if (empty($to)) {
            throw new RuntimeException(
                'Outbox tidak memiliki alamat tujuan.'
            );
        }

        $mailerName =
            'user_email_delivery_'
            .$account->id
            .'_'
            .Str::lower(
                Str::random(8)
            );

        $this->configureMailer(
            $mailerName,
            $account
        );

        $email =
            new Email();

        $email->from(
            new Address(
                $account->email_address
            )
        );

        foreach ($to as $address) {
            $email->addTo(
                new Address(
                    $address['email'],
                    $address['name']
                        ?? ''
                )
            );
        }

        foreach ($cc as $address) {
            $email->addCc(
                new Address(
                    $address['email'],
                    $address['name']
                        ?? ''
                )
            );
        }

        $email
            ->subject(
                (string) $message->subject
            )
            ->text(
                (string) $message->text_body
            )
            ->html(
                (string) (
                    $message->html_body
                    ?: nl2br(
                        e(
                            (string) $message
                                ->text_body
                        )
                    )
                )
            );

        if ($replyTo?->message_id) {
            $messageIdHeader =
                $this->messageIdHeader(
                    $replyTo->message_id
                );

            $email
                ->getHeaders()
                ->addTextHeader(
                    'In-Reply-To',
                    $messageIdHeader
                );

            $existingReferences =
                trim(
                    (string) (
                        $replyTo
                            ->references_header
                        ?? ''
                    )
                );

            $references =
                trim(
                    $existingReferences
                    .' '
                    .$messageIdHeader
                );

            $email
                ->getHeaders()
                ->addTextHeader(
                    'References',
                    $references
                );

            $message->in_reply_to =
                trim(
                    $replyTo->message_id,
                    '<>'
                );

            $message->references_header =
                $references;
        }

        $storedAttachments =
            UserEmailAttachment::query()
                ->where(
                    'message_id',
                    $message->id
                )
                ->get();

        foreach ($storedAttachments as $attachment) {
            $absolute =
                storage_path(
                    'app/'
                    .str_replace(
                        '\\',
                        '/',
                        $attachment->storage_path
                    )
                );

            if (! is_file($absolute)) {
                continue;
            }

            $email->attachFromPath(
                $absolute,
                $attachment->original_name,
                $attachment->mime_type
                    ?: 'application/octet-stream'
            );
        }

        $message->delivery_attempts =
            (int) (
                $message->delivery_attempts
                ?? 0
            )
            + 1;

        $message->delivery_status =
            'sending';

        $message->delivery_error =
            null;

        $message->save();

        try {
            $transport =
                Mail::mailer(
                    $mailerName
                )->getSymfonyTransport();

            $sent =
                $transport->send(
                    $email
                );

            $sentMessageId =
                method_exists(
                    $sent,
                    'getMessageId'
                )
                    ? $sent->getMessageId()
                    : null;

            /*
             * MY_EMAIL_FOLDER_UID_TRANSITION_FIX_V1
             *
             * imap_uid is unique inside (account_id, folder).  The OUTBOX UID
             * must therefore never be carried into CRM_SENT.  This message
             * already has a database id, which is monotonic and unique, so it
             * is a safe local UID for the CRM_SENT namespace and avoids the
             * duplicate-key failure seen when OUTBOX uid 1/2 collided with
             * existing CRM_SENT uid 1/2.
             */
            $message->imap_uid =
                (int) $message->id;

            $message->folder =
                'CRM_SENT';

            $message->delivery_status =
                'sent';

            $message->delivery_error =
                null;

            $message->failed_at =
                null;

            $message->sent_at =
                now();

            $message->received_at =
                $message->received_at
                ?: now();

            $message->read_at =
                now();

            if ($sentMessageId) {
                $message->message_id =
                    $sentMessageId;
            }

            $message->save();

            return $message;
        } catch (Throwable $exception) {
            $message->folder =
                'OUTBOX';

            $message->delivery_status =
                'failed';

            $message->delivery_error =
                $exception->getMessage();

            $message->failed_at =
                now();

            $message->save();

            throw new RuntimeException(
                'Email tidak terkirim dan disimpan di Outbox: '
                .$exception->getMessage(),
                0,
                $exception
            );
        } finally {
            Mail::purge(
                $mailerName
            );
        }
    }

    private function configureMailer(
        string $mailerName,
        UserEmailAccount $account
    ): void {
        $encryption =
            strtolower(
                trim(
                    (string) $account
                        ->smtp_encryption
                )
            );

        if ($encryption === 'starttls') {
            $encryption = 'tls';
        }

        if ($encryption === 'none') {
            $encryption = null;
        }

        Config::set(
            'mail.mailers.'
            .$mailerName,
            [
                'transport' => 'smtp',
                'host' => $account->smtp_host,
                'port' => (int) $account->smtp_port,
                'encryption' => $encryption,
                'username' =>
                    $account
                        ->smtpUsernameValue(),
                'password' =>
                    $account
                        ->smtpPasswordValue(),
                'timeout' => 30,
                'local_domain' =>
                    parse_url(
                        (string) config(
                            'app.url'
                        ),
                        PHP_URL_HOST
                    ),
            ]
        );

        Mail::purge(
            $mailerName
        );
    }

    private function nextLocalUid(
        int $accountId,
        string $folder
    ): int {
        return DB::transaction(
            function () use (
                $accountId,
                $folder
            ) {
                $last =
                    (int) UserEmailMessage::query()
                        ->where(
                            'account_id',
                            $accountId
                        )
                        ->where(
                            'folder',
                            $folder
                        )
                        ->lockForUpdate()
                        ->max(
                            'imap_uid'
                        );

                return $last + 1;
            }
        );
    }

    private function decodeAddresses(
        mixed $json
    ): array {
        if (
            $json === null
            || $json === ''
        ) {
            return [];
        }

        if (is_array($json)) {
            return $json;
        }

        $decoded =
            json_decode(
                (string) $json,
                true
            );

        return is_array($decoded)
            ? $decoded
            : [];
    }

    private function messageIdHeader(
        string $messageId
    ): string {
        $messageId =
            trim($messageId);

        if (
            str_starts_with($messageId, '<')
            && str_ends_with($messageId, '>')
        ) {
            return $messageId;
        }

        return '<'
            .trim(
                $messageId,
                '<>'
            )
            .'>';
    }
}
