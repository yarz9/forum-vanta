<?php

namespace Vanta\Forum;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    protected $nodeStructure = [
        [
            'title' => 'Administration',
            'slug' => 'administration',
            'order' => 100,
            'description' => 'Official announcements, changelog and rules.',
            'forums' => [
                ['title' => 'Announcements', 'slug' => 'announcements', 'order' => 10, 'description' => 'Official Vanta announcements.'],
                ['title' => 'Changelog', 'slug' => 'changelog', 'order' => 20, 'description' => 'Loader and forum changes.'],
                ['title' => 'Rules', 'slug' => 'rules', 'order' => 30, 'description' => 'Community and service rules.'],
                ['title' => 'Frequently Asked Questions', 'slug' => 'frequently-asked-questions', 'order' => 40, 'description' => 'Answers to common questions about the loader and forum.'],
                ['title' => 'Billing & Purchases', 'slug' => 'billing-purchases', 'order' => 50, 'description' => 'Purchase information, crypto payments and billing.'],
            ],
        ],
        [
            'title' => 'Public',
            'slug' => 'public',
            'order' => 200,
            'description' => 'Open community sections.',
            'forums' => [
                ['title' => 'General Talk', 'slug' => 'general-talk', 'order' => 10, 'description' => 'General discussion.'],
                ['title' => 'Reselling', 'slug' => 'reselling', 'order' => 20, 'description' => 'Reselling and trading talk.'],
                ['title' => 'Media', 'slug' => 'media', 'order' => 30, 'description' => 'Clips, screenshots and showcases.'],
                ['title' => 'Introductions', 'slug' => 'introductions', 'order' => 40, 'description' => 'Introduce yourself to the community.'],
            ],
        ],
        [
            'title' => 'Premium',
            'slug' => 'premium',
            'order' => 300,
            'description' => 'Sections for Premium members.',
            'forums' => [
                ['title' => 'Loader Download', 'slug' => 'loader-download', 'order' => 10, 'description' => 'Download the loader and get setup help.'],
                ['title' => 'Configs', 'slug' => 'configs', 'order' => 20, 'description' => 'Share and request configs.'],
                ['title' => 'Bug Reports', 'slug' => 'premium-bug-reports', 'order' => 30, 'description' => 'Report bugs in the loader.'],
                ['title' => 'Suggestions', 'slug' => 'suggestions', 'order' => 40, 'description' => 'Suggest improvements for the loader and forum.'],
                ['title' => 'Testimonials', 'slug' => 'premium-testimonials', 'order' => 50, 'description' => 'Share your experience with Vanta.'],
            ],
        ],
        [
            'title' => 'Beta',
            'slug' => 'beta',
            'order' => 400,
            'description' => 'Beta release sections.',
            'forums' => [
                ['title' => 'Updates', 'slug' => 'beta-updates', 'order' => 10, 'description' => 'Beta build updates.'],
                ['title' => 'Rules', 'slug' => 'beta-rules', 'order' => 20, 'description' => 'Beta section rules.'],
                ['title' => 'Bug Reports', 'slug' => 'beta-bug-reports', 'order' => 30, 'description' => 'Report bugs in the beta build.'],
                ['title' => 'Testimonials', 'slug' => 'beta-testimonials', 'order' => 40, 'description' => 'Beta tester feedback.'],
                ['title' => 'Chat', 'slug' => 'beta-chat', 'order' => 50, 'description' => 'Off-topic chat for beta testers.'],
            ],
        ],
        [
            'title' => 'LAFF',
            'slug' => 'laff',
            'order' => 500,
            'description' => 'Friends-only alpha section.',
            'forums' => [
                ['title' => 'Updates', 'slug' => 'laff-updates', 'order' => 10, 'description' => 'Alpha build updates.'],
                ['title' => 'Known Issues', 'slug' => 'laff-known-issues', 'order' => 20, 'description' => 'Known issues in the alpha.'],
                ['title' => 'Roadmap', 'slug' => 'laff-roadmap', 'order' => 30, 'description' => 'Planned work for the alpha.'],
                ['title' => 'Bug Reports', 'slug' => 'laff-bug-reports', 'order' => 40, 'description' => 'Report alpha bugs.'],
                ['title' => 'Testimonials', 'slug' => 'laff-testimonials', 'order' => 50, 'description' => 'Alpha feedback.'],
                ['title' => 'Chat', 'slug' => 'laff-chat', 'order' => 60, 'description' => 'Alpha chat.'],
            ],
        ],
        [
            'title' => 'Staff',
            'slug' => 'staff',
            'order' => 600,
            'description' => 'Internal staff area.',
            'forums' => [
                ['title' => 'Staff Chat', 'slug' => 'staff-chat', 'order' => 10, 'description' => 'Internal staff chat.'],
                ['title' => 'Reports & Appeals', 'slug' => 'staff-reports-appeals', 'order' => 20, 'description' => 'User reports and ban appeals.'],
                ['title' => 'Internal', 'slug' => 'staff-internal', 'order' => 30, 'description' => 'Internal staff documentation.'],
            ],
        ],
    ];

    public function install(array $stepParams = [])
    {
        $this->seedNodes();
        $this->seedWidgets();
        $this->configureMemberPages();
        $this->repairBrokenAvatars();
    }

    public function upgrade(array $stepParams = [], &$previousVersion = false)
    {
        $this->seedNodes();
        $this->seedWidgets();
        $this->configureMemberPages();
        $this->repairBrokenAvatars();
    }

    public function uninstall(array $stepParams = [])
    {
        // intentionally left blank; user content is kept when the add-on is removed
    }

    protected function seedNodes()
    {
        foreach ($this->nodeStructure AS $category)
        {
            $parentId = 0;

            $existing = $this->findNode($category['title'], 0);

            if ($existing)
            {
                $parentId = (int) $existing['node_id'];
                $this->updateNodeOrder($parentId, $category['order']);
            }
            else
            {
                $parentId = $this->createNode(
                    $category['title'],
                    'Category',
                    $category['slug'],
                    $category['order'],
                    $category['description'],
                    0
                );
            }

            foreach ($category['forums'] AS $forum)
            {
                $existingForum = $this->findNode($forum['title'], $parentId);

                if ($existingForum)
                {
                    $this->updateNodeOrder((int) $existingForum['node_id'], $forum['order']);
                    continue;
                }

                $this->createNode(
                    $forum['title'],
                    'Forum',
                    $forum['slug'],
                    $forum['order'],
                    $forum['description'],
                    $parentId
                );
            }
        }
    }

    protected function createNode(string $title, string $type, string $slug, int $order, string $description, int $parentId): int
    {
        $em = \XF::em();

        /** @var \XF\Entity\Node $node */
        $node = $em->create('XF:Node');
        $node->node_type_id = $type;
        $node->parent_node_id = $parentId;
        $node->title = $title;
        $node->description = $description;
        $node->display_order = $order;
        $node->display_in_list = true;
        $node->node_name = $this->uniqueNodeName($slug);

        $data = $node->getDataRelationOrDefault(false);
        $node->addCascadedSave($data);
        $node->save();

        return (int) $node->node_id;
    }

    protected function uniqueNodeName(string $slug): string
    {
        $db = $this->app->db();
        $name = $slug;
        $attempt = 2;

        while ($db->fetchOne("SELECT node_id FROM xf_node WHERE node_name = ?", [$name]))
        {
            $name = $slug . '-' . $attempt++;
        }

        return $name;
    }

    protected function findNode(string $title, int $parentId): ?array
    {
        $node = $this->app->db()->fetchRow("
            SELECT *
            FROM xf_node
            WHERE title = ? AND COALESCE(parent_node_id, 0) = ?
            LIMIT 1
        ", [$title, $parentId]);

        return $node ?: null;
    }

    protected function updateNodeOrder(int $nodeId, int $order)
    {
        $this->app->db()->update('xf_node', [
            'display_order' => $order,
        ], 'node_id = ? AND display_order <> ?', [$nodeId, $order]);
    }

    protected function seedWidgets()
    {
        $db = $this->app->db();
        $em = \XF::em();

        $widgets = [
            'members_online' => ['forum_list_sidebar'],
            'forum_statistics' => ['forum_list_sidebar'],
            'new_posts' => ['forum_list_sidebar'],
        ];

        foreach ($widgets AS $key => $positions)
        {
            if ($db->fetchOne("SELECT widget_id FROM xf_widget WHERE widget_key = ?", [$key]))
            {
                continue;
            }

            /** @var \XF\Entity\Widget $widget */
            $widget = $em->create('XF:Widget');
            $widget->widget_key = $key;
            $widget->definition_id = $key;
            $widget->positions = $positions;
            $widget->options = [];
            $widget->save();
        }
    }

    protected function configureMemberPages()
    {
        $db = $this->app->db();

        \XF::repository(\XF\Repository\OptionRepository::class)->updateOption('enableMemberList', true);

        $hasEntries = (int) $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_permission_entry
            WHERE permission_group_id = 'general'
              AND permission_id = 'viewMemberList'
              AND user_id = 0
        ");

        if ($hasEntries)
        {
            return;
        }

        foreach ([2, 3] AS $groupId)
        {
            $db->insert('xf_permission_entry', [
                'user_group_id' => $groupId,
                'user_id' => 0,
                'permission_group_id' => 'general',
                'permission_id' => 'viewMemberList',
                'permission_value' => 'allow',
                'permission_value_int' => 1,
            ]);
        }
    }

    protected function repairBrokenAvatars()
    {
        $db = $this->app->db();
        $root = \XF::getRootDirectory();

        $users = $db->fetchAll("
            SELECT user_id
            FROM xf_user
            WHERE avatar_date > 0 AND avatar_type = 'custom'
        ");

        foreach ($users AS $user)
        {
            $userId = (int) $user['user_id'];
            $bucket = floor($userId / 1000);

            $medium = $root . "/data/avatars/m/{$bucket}/{$userId}.jpg";
            $large = $root . "/data/avatars/l/{$bucket}/{$userId}.jpg";

            if (file_exists($medium) || file_exists($large))
            {
                continue;
            }

            $db->update('xf_user', [
                'avatar_date' => 0,
                'avatar_width' => 0,
                'avatar_height' => 0,
            ], 'user_id = ?', $userId);
        }
    }
}
