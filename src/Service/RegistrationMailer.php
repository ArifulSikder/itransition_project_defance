<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class RegistrationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly Environment $twig,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $dsn,
    ) {
    }

    public function showsLinkOnPage(): bool
    {
        return str_starts_with($this->dsn, 'null:');
    }

    public function confirmationUrl(User $user): string
    {
        return $this->urls->generate('app_verify_email', [
            'token' => $user->getConfirmationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function send(User $user): void
    {
        $url = $this->confirmationUrl($user);
        $email = (new Email())
            ->from('CV Hub <noreply@cvhub.test>')
            ->to($user->getEmail())
            ->subject('Confirm your CV Hub account')
            ->html($this->twig->render('email/confirm.html.twig', [
                'user' => $user,
                'url' => $url,
            ]));

        $this->mailer->send($email);
    }
}
