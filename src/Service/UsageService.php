<?php

namespace App\Service;

use App\Config\AppConfig;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UsageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AppConfig $config
    ) {
    }

    public function getUserUsage(User $user): array
    {
        $usedBytes = (int) $this->entityManager
            ->getConnection()
            ->fetchOne(
                'SELECT COALESCE(SUM(size), 0)
                 FROM files
                 WHERE user_id = ?',
                [$user->getId()]
            );

        $plan = $user->getPlan();

        $storageLimit = (int) $this->config->get(
            'plans.' . $plan . '.storage_limit',
            0
        );

        $maxFileSize = (int) $this->config->get(
            'plans.' . $plan . '.max_file_size',
            0
        );

        return [
            'plan' => $plan,
            'used_bytes' => $usedBytes,
            'storage_limit_bytes' => $storageLimit,
            'remaining_bytes' => max(0, $storageLimit - $usedBytes),
            'max_file_size_bytes' => $maxFileSize,
            'usage_percent' => $storageLimit > 0
                ? round(($usedBytes / $storageLimit) * 100, 2)
                : 0,
        ];
    }

    public function assertCanUpload(User $user, int $newFileSize): void
    {
        $usage = $this->getUserUsage($user);

        if ($newFileSize > $usage['max_file_size_bytes']) {
            throw new \RuntimeException(
                'File exceeds maximum allowed size for current plan'
            );
        }

        if ($usage['used_bytes'] + $newFileSize > $usage['storage_limit_bytes']) {
            throw new \RuntimeException(
                'Storage limit exceeded'
            );
        }
    }
}