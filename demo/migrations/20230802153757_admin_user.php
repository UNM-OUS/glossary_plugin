<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AdminUser extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user')
            ->insert([
                'uuid' => 'demoadmin',
                'name' => 'Demo Administrator',
                'data' => '{}',
                'created' => time(),
                'created_by' => 'system',
                'updated' => time(),
                'updated_by' => 'system'
            ])
            ->save();
        $this->table('user_source')
            ->insert([
                'user_uuid' => 'demoadmin',
                'source' => 'cas',
                'provider' => 'demo',
                'provider_id' => 'admin',
                'created' => time()
            ])
            ->save();
        $this->table('user_group_membership')
            ->insert([
                'user_uuid' => 'demoadmin',
                'group_uuid' => 'admins'
            ])
            ->save();
    }
}
