<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SettingsApiTest extends WebTestCase
{
    private function rebuildSchema(EntityManagerInterface $em): void
    {
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private function createUser(EntityManagerInterface $em): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hash-not-used');
        $user->setFullName('Test User');
        $user->setBusinessName('Test Biz');
        $user->setBusinessPhone('0600000000');
        $user->setCity('Casablanca');
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testGetReturnsDefaults(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->rebuildSchema($em);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('GET', '/api/settings');
        self::assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('parcel_settings', $data);
        self::assertArrayHasKey('packaging_settings', $data);
        self::assertSame('none', $data['packaging_settings']['cartons']['selected']);
        self::assertFalse($data['packaging_settings']['cartons']['enabled']);
    }

    public function testPutUpdatesPackagingSelection(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->rebuildSchema($em);

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('PUT', '/api/settings', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'packaging_settings' => [
                'cartons' => ['selected' => 'M'],
                'sachets' => ['selected' => 'none'],
                'bubble_wrap' => ['selected' => 'L'],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('M', $data['packaging_settings']['cartons']['selected']);
        self::assertTrue($data['packaging_settings']['cartons']['enabled']);

        self::assertSame('none', $data['packaging_settings']['sachets']['selected']);
        self::assertFalse($data['packaging_settings']['sachets']['enabled']);

        self::assertSame('L', $data['packaging_settings']['bubble_wrap']['selected']);
        self::assertTrue($data['packaging_settings']['bubble_wrap']['enabled']);
    }
}

