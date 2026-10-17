<?php

namespace XenSoluce\InviteSystem\Admin\Controller;

use XenSoluce\InviteSystem\Service\InvitationEmail;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;
use XenSoluce\InviteSystem\Entity\Token;
use XenSoluce\InviteSystem\Entity\Banning;
use XenSoluce\InviteSystem\Entity\CodeInvitation;
use XenSoluce\InviteSystem\Entity\PersonalizedInvitationCode;

class Invitation  extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('xsISmanageInvitation');
    }

    public function actionIndex()
	{
        return $this->view('XenSoluce\InviteSystem:Invitation', 'xs_is_invitation');
	}

	/**Tokens*/
    public function actionTokens(ParameterBag $params)
    {
        if ($params->token_id)
        {
            $Token = $this->assertTokenExists($params['token_id']);
            if($Token->type_token == '2')
            {
                return $this->redirect($this->buildLink('invitation/tokens-user',$Token));
            }
            else
            {
                return $this->rerouteController(__CLASS__, 'TokensEdit', $params);
            }
        }

        $Tokens = $this->finder('XenSoluce\InviteSystem:Token')->where('type_token', '1')->order('title', 'ASC');
        $viewParams = [
            'tokens' => $Tokens->fetch(),
            'userGroups' => $this->em()->getRepository('XF:UserGroup')->getUserGroupTitlePairs()
        ];

        return $this->view('XenSoluce\InviteSystem:Invitation\Tokens\Listing', 'xs_is_token_list', $viewParams);
    }

    protected function tokenAddEdit(Token $Token)
    {
        $userGroupRepo = \XF::repository('XF:UserGroup');
        $viewParams = [
            'token' => $Token,
            'userGroups' => $this->em()->getRepository('XF:UserGroup')->getUserGroupTitlePairs(),
            'UserGroups' => $userGroupRepo->findUserGroupsForList()->fetch()
        ];

        return $this->view('XenSoluce\InviteSystem:Invitation\Tokens\Edit', 'xs_is_token_edit', $viewParams);
    }

    public function actionTokensEdit(ParameterBag $params)
    {
        $Token = $this->assertTokenExists($params['token_id']);
        return $this->tokenAddEdit($Token);
    }

    public function actionTokensAdd()
    {
        $Token = $this->em()->create('XenSoluce\InviteSystem:Token');
        return $this->tokenAddEdit($Token);
    }

    protected function TokensSaveProcess(Token $Token)
    {
        $form = $this->formAction();
        $entityInput = $this->filter([
            'title' => 'str',
            'user' => 'array-int',
            'number_use' => 'int'
        ]);
        $Token->set('type_token', '1');
        $entityInput += $this->saveUserGroup();
        $form->basicEntitySave($Token, $entityInput);

        return $form;
    }

    public function actionTokensSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->token_id)
        {
            $Token = $this->assertTokenExists($params['token_id']);
        }
        else
        {
            $Token = $this->em()->create('XenSoluce\InviteSystem:Token');
        }
        $this->TokensSaveProcess($Token)->run();

        return $this->redirect($this->buildLink('invitation/tokens') . $this->buildLinkHash($Token->token_id));
    }

    public function actionTokensDelete(ParameterBag $params)
    {
        $Token = $this->assertTokenExists($params['token_id']);
        if($Token->type_token == '2'){
            return $this->redirect($this->buildLink('invitation/tokens-user/Delete',$Token));
        }
        if ($this->isPost())
        {
            $Token->delete();

            return $this->redirect($this->buildLink('invitation/tokens') . $this->buildLinkHash('delete:' . $Token->token_id));
        }
        else
        {
            $viewParams = [
                'token' => $Token
            ];
            return $this->view('XenSoluce\InviteSystem:Invitation\Tokens\Delete', 'xs_is_token_delete', $viewParams);
        }
    }

    public function actionTokensList(ParameterBag $params)
    {
        if ($params->code_id)
        {
            $code = $this->assertListCodeExists($params->code_id);
            $viewParams = [ 'code' => $code ];
            return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\View', 'xs_is_tokens_view_code', $viewParams);
        }
        else
        {
            $Token = $this->assertTokenExists($params['token_id']);
            if($Token->type_token === '2')
            {
                return $this->redirect($this->buildLink('invitation/tokens-user/list',$Token));
            }
            $filters = $this->getListCodeFilterInput();
            $page = $this->filterPage();
            $perPage = 20;
            $codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where('token_id', $Token->token_id)
                ->order('invitation_date', 'DESC');
            $user = $this->finder('XF:User');
            if (!empty($filters['user'])) {
                $user = $this->finder('XF:User')->where('username', $filters['user'])->fetchOne();
            }

            $this->applyListCodeFilters($codes, $filters, $user);
            $codes->limitByPage($page, $perPage);
            $total = $codes->total();

            $viewParams = [
                'Token' => $Token,
                'codes' => $codes->fetch(),
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'filters' => $filters,
                'user' => $user
            ];

            return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\Listing', 'xs_is_tokens_list_code', $viewParams);
        }
    }

    public function actionTokensDeleteCode(ParameterBag $params)
    {
        $code = $this->assertListCodeExists($params->code_id);
        $Token = $this->assertTokenExists($code->token_id);

        if ($this->isPost())
        {
            $code->delete();
            if($Token->type_token == '2')
            {
                return $this->redirect($this->buildLink('invitation/tokens-user/list', $Token) . $this->buildLinkHash('delete:' . $code->code));
            }
            return $this->redirect($this->buildLink('invitation/tokens/list', $Token) . $this->buildLinkHash('delete:' . $code->code));
        }
        else
        {
            $viewParams = [
                'Token' => $Token,
                'code' => $code
            ];

            return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\Delete', 'xs_is_tokens_delete_code', $viewParams);
        }
    }

    public function actionTokensFilters(ParameterBag $params)
    {
        $Token = $this->assertTokenExists($params['token_id']);
        if($Token->type_token == '2')
        {
            return $this->redirect($this->buildLink('invitation/tokens',$Token));
        }
        $filters = $this->getListCodeFilterInput();
        if ($this->filter('apply', 'bool'))
        {
            return $this->redirect($this->buildLink('invitation/tokens/list', $Token, $filters));
        }
        $user['username'] = "";
        if(!empty($filters['user']))
        {
            $user = $this->finder('XF:User')->where('username', $filters['user'])->fetchOne();
        }

        $viewParams = [
            'filters' => $filters,
            'Token' => $Token,
            'user' => $user
        ];
        return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\Filters', 'xs_is_tokens_filters', $viewParams);
    }

    /**Tokens User*/
    public function actionTokensUser(ParameterBag $params)
    {
        if ($params->token_id)
        {
            $Token = $this->assertTokenExists($params['token_id']);
            if($Token->type_token == '1')
            {
                return $this->redirect($this->buildLink('invitation/tokens',$Token));
            }
            else
            {
                return $this->rerouteController(__CLASS__, 'TokensUserEdit', $params);
            }
        }
        $Tokens = $this->finder('XenSoluce\InviteSystem:Token')->order('title', 'ASC')->where('type_token', '2');
        $count = [];
        foreach ($Tokens as $Token)
        {
            $count[$Token->token_id] = count($Token->user);
        }
        $viewParams = [
            'tokens' => $Tokens->fetch(),
            'count' => $count
        ];

        return $this->view('XenSoluce\InviteSystem:Invitation\TokensUser\Listing', 'xs_is_token_user_list', $viewParams);
    }

    /**
     * @param Token $Token
     * @return \XF\Mvc\Reply\View
     */
    protected function tokensUserAddEdit(Token $Token)
    {
        $page = $this->filterPage();
        $perPage = 20;
        $users = $this->finder('XF:User')
            ->where('user_id', $Token->user)
            ->limitByPage($page, $perPage);
        $CodeUser = [];
        if($Token->user){
            foreach ($Token->user as $user)
            {
                $codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                    ->where([
                        'user_id'=> $user,
                        'token_id' => $Token->token_id
                    ])->fetch();
                foreach ($codes as $code)
                {
                    if($code){
                        $CodeUser[$user]['code'] = true;
                        if($code->registered_user_id)
                        {
                            $CodeUser[$user]['registered'] = true;
                        }
                        else
                        {
                            $CodeUser[$user]['registered'] = false;
                        }
                    }
                    else
                    {
                        $CodeUser[$user]['code'] = false;
                    }
                }
                $CodeUser[$user]['count']  = count($codes);
            }
        }
        $userGroupRepo = \XF::repository('XF:UserGroup');
        $viewParams = [
            'token' => $Token,
            'users' => $users->fetch(),
            'total' => $users->total(),
            'page' => $page,
            'perPage' => $perPage,
            'CodeUser' => $CodeUser,
            'UserGroups' => $userGroupRepo->findUserGroupsForList()->fetch()
        ];

        return $this->view('XenSoluce\InviteSystem:Invitation\TokensUser\Edit', 'xs_is_token_user_edit', $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionTokensUserEdit(ParameterBag $params)
    {
        /** @var Token $Token */
        $Token = $this->assertTokenExists($params['token_id']);
        return $this->tokensUserAddEdit($Token);
    }

    public function actionTokensUserAdd()
    {
        /** @var Token $Token */
        $Token = $this->em()->create('XenSoluce\InviteSystem:Token');
        return $this->tokensUserAddEdit($Token);
    }
    protected function TokensUserSaveProcess(Token $Token, $action)
    {
        $form = $this->formAction();
        $input = $this->filter(['title'=> 'str','number_use' => 'int']);
        if($action === 'add')
        {
            $username = $this->filter('username', 'str');
            $username = explode(',', $username);
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $users = $userRepo->getUsersByNames($username, $notFound);

            $new = [];
            foreach ($users as $user)
            {
                $new[] = $user->user_id;
            }
            $entity = [
                'title' => $input['title'],
                'user' => $new,
                'type_token' => '2',
                'number_use' => $input['number_use']
            ];
        }
        else
        {
            $entity = [
                'title' => $input['title'],
                'number_use' => $input['number_use']
            ];
        }
        $entity += $this->saveUserGroup();
        $form->basicEntitySave($Token, $entity);
        return $form;
    }
    protected function saveUserGroup()
    {
        $userGroupCode = $this->filter([
            'enable_add_user_group' => 'int',
            'type_user_group' => [
                'first_group' => 'int',
                'secondary_group' => 'int',
            ],
            'user_group' => [
                'first_user_group_id' => 'int',
                'secondary_user_group_id' => 'array-uint'
            ]
        ]);
        $entity = [];
        if($userGroupCode['enable_add_user_group'])
        {
            if($userGroupCode['type_user_group']['first_group'] && $userGroupCode['type_user_group']['secondary_group'])
            {
                $entity['type_user_group'] = 'all';
            }
            elseif ($userGroupCode['type_user_group']['first_group'])
            {
                $entity['type_user_group'] = 'first';
            }
            elseif ($userGroupCode['type_user_group']['secondary_group'])
            {
                $entity['type_user_group'] = 'secondary';
            }
            $entity['user_group'] = $userGroupCode['user_group']['first_user_group_id'];
            $entity['secondary_user_group'] = $userGroupCode['user_group']['secondary_user_group_id'];
        }
        $entity['enable_add_user_group'] = $userGroupCode['enable_add_user_group'];
        return $entity;
    }
    public function actionTokensUserSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->token_id)
        {
            $Token = $this->assertTokenExists($params['token_id']);
            $action = 'edit';
        }
        else
        {
            $Token = $this->em()->create('XenSoluce\InviteSystem:Token');
            $action = 'add';
        }
        $this->TokensUserSaveProcess($Token, $action)->run();

        return $this->redirect($this->buildLink('invitation/tokens-user') . $this->buildLinkHash($Token->token_id));
    }
    public function actionTokensUserDelete(ParameterBag $params)
    {
        $Token = $this->assertTokenExists($params['token_id']);
        if($Token->type_token == '1')
        {
            return $this->redirect($this->buildLink('invitation/tokens/delete',$Token));
        }
        if ($this->isPost())
        {
            $Token->delete();

            return $this->redirect($this->buildLink('invitation/tokens-user') . $this->buildLinkHash('delete:' . $Token->token_id));
        }
        else
        {
            $viewParams = [
                'token' => $Token
            ];
            return $this->view('XenSoluce\InviteSystem:Invitation\Tokens\Delete', 'xs_is_token_user_delete', $viewParams);
        }
    }
    public function actionTokensUserAddUser(ParameterBag $params)
    {
        $Token = $this->assertTokenExists($params['token_id']);
        if($Token->type_token == '1')
        {
            return $this->error(\XF::phrase('requested_page_not_found'));
        }
        if ($this->isPost())
        {
            $username = $this->filter('username', 'str');
            $username = explode(',', $username);
            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $users = $userRepo->getUsersByNames($username, $notFound);
            $new = [];
            $error = [];
            foreach ($users as $user)
            {
                if(in_array($user->user_id, $Token->user))
                {
                    $error[] = $user->username;
                }
                $new[] = $user->user_id;
            }
            if(!empty($error))
            {
                return $this->error(\XF::phrase('xs_is_user_already_added', [
                    'username' => implode(', ', $error)
                ]));
            }
            else
            {
                $Token->user = array_merge($Token->user, $new);
                $Token->save();
                return $this->redirect($this->buildLink('invitation/tokens-user', $Token));
            }

        }
        else
        {
            $viewParams = [
                'token' => $Token
            ];
            return $this->view('XenSoluce\InviteSystem:Invitation\TokensUser\Delete', 'xs_is_token_user_add_user', $viewParams);
        }
    }
    public function actionTokensUserDeleteUser(ParameterBag $params)
    {
        $Token = $this->assertTokenExists($params['token_id']);
        if($Token->type_token == '1')
        {
            return $this->error(\XF::phrase('requested_page_not_found'));
        }
        $userID = $this->filter('user', 'int');
        if(empty($userID)){
            return $this->error(\XF::phrase('requested_page_not_found'));
        }
        if ($this->isPost())
        {
            $users = $Token['user'];
            unset($users[array_search($userID, $users)]);
            $Token->user = $users;
            $Token->save();
            return $this->redirect($this->buildLink('invitation/tokens-user', $Token));
        }
        else
        {
            $user = $this->finder('XF:User')->where('user_id', $userID)->fetchOne();
            $viewParams = [
                'token' => $Token,
                'user' => $user
            ];
            return $this->view('XenSoluce\InviteSystem:Invitation\TokensUser\Delete', 'xs_is_token_user_delete_user', $viewParams);
        }
    }
    public function actionTokensUserList(ParameterBag $params)
    {
        if ($params->code_id)
        {
            $code = $this->assertListCodeExists($params->code_id);
            $viewParams = [ 'code' => $code ];
            return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\View', 'xs_is_token_user_view_code', $viewParams);
        }
        else
        {
            $Token = $this->assertTokenExists($params['token_id']);
            if($Token->type_token == '1')
            {
                return $this->redirect($this->buildLink('invitation/tokens/list',$Token));
            }
            $filters = $this->getListCodeFilterInput();
            $page = $this->filterPage();
            $perPage = 20;
            $codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where('token_id', $Token->token_id)
                ->order('invitation_date', 'DESC');
            $user = $this->finder('XF:User');
            if (!empty($filters['user'])) {
                $user = $this->finder('XF:User')->where('username', $filters['user'])->fetchOne();
            }

            $this->applyListCodeFilters($codes, $filters, $user);
            $codes->limitByPage($page, $perPage);
            $total = $codes->total();

            $viewParams = [
                'Token' => $Token,
                'codes' => $codes->fetch(),
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'filters' => $filters,
                'user' => $user
            ];

            return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\Listing', 'xs_is_token_user_list_code', $viewParams);
        }
    }
   public function actionTokensUserFilters(ParameterBag $params)
    {
        $Token = $this->assertTokenExists($params['token_id']);
        if($Token->type_token == '1')
        {
            return $this->redirect($this->buildLink('invitation/tokens/list',$Token));
        }
        $filters = $this->getListCodeFilterInput();

        if ($this->filter('apply', 'bool'))
        {
            return $this->redirect($this->buildLink('invitation/tokens-user/list', $Token, $filters));
        }
        $user['username'] = "";
        if(!empty($filters['user']))
        {
            $user = $this->finder('XF:User')->where('username', $filters['user'])->fetchOne();
        }

        $viewParams = [
            'filters' => $filters,
            'Token' => $Token,
            'user' => $user
        ];
        return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\Filters', 'xs_is_token_user_filters', $viewParams);
    }

    /** Personalized invitation code */

    public function actionPersonalizedInvitationCode(ParameterBag $params)
    {
        if ($params->ic_personalize_id)
        {
            return $this->rerouteController(__CLASS__, 'PersonalizedInvitationCodeEdit', $params);
        }
        $Invitations = $this->finder('XenSoluce\InviteSystem:PersonalizedInvitationCode')->fetch();
        $valid = [];
        foreach ($Invitations as $Invitation)
        {
            $valid[$Invitation->ic_personalize_id] = true;
            if($Invitation->limit_use >= 1)
            {
                if(count($Invitation->registered_user_id) >= $Invitation->limit_use )
                {
                    $valid[$Invitation->ic_personalize_id] = false;
                }
            }
            if($Invitation->limit_time >= 1)
            {
                if($Invitation->limit_time <= \XF::$time )
                {
                    $valid[$Invitation->ic_personalize_id] = false;
                }
            }
        }
        $viewParams = [
            'Invitations' => $Invitations,
            'validCode' => $valid
        ];
        return $this->view('XenSoluce\InviteSystem:Invitation\PersonalizedInvitationCode\Listing', 'xs_is_personalized_invitation_code', $viewParams);
    }
    protected function PersonalizedInvitationCodeAddEdit(PersonalizedInvitationCode $code, $UserGroupCode)
    {
        $page = $this->filterPage();
        $perPage = 20;
        $users = $this->finder('XF:User')
            ->where('user_id', $code->registered_user_id)
            ->limitByPage($page, $perPage);
        $userGroupRepo = \XF::repository('XF:UserGroup');
        $viewParams = [
            'users' => $users->fetch(),
            'total' => $users->total(),
            'page' => $page,
            'perPage' => $perPage,
            'code' => $code,
            'UserGroups' => $userGroupRepo->findUserGroupsForList()->fetch(),
            'UserGroupCode' => $UserGroupCode
        ];

        return $this->view('XenSoluce\InviteSystem:Invitation\PersonalizedInvitationCode\Edit', 'xs_is_personalized_invitation_code_edit', $viewParams);
    }
    public function actionPersonalizedInvitationCodeEdit(ParameterBag $params)
    {
        /** @var PersonalizedInvitationCode $code */
        $code = $this->assertPersonalizedInvitationCodeExists($params->ic_personalize_id);
        $UserGroupCode = $this->finder('XenSoluce\InviteSystem:UserGroupCode')
            ->where('entity_id', '=', $code->ic_personalize_id)->fetchOne();
        return $this->PersonalizedInvitationCodeAddEdit($code, $UserGroupCode);
    }
    public function actionPersonalizedInvitationCodeAdd()
    {
        /** @var PersonalizedInvitationCode $code */
        $code = $this->em()->create('XenSoluce\InviteSystem:PersonalizedInvitationCode');
        $UserGroupCode = $this->em()->create('XenSoluce\InviteSystem:UserGroupCode');
        return $this->PersonalizedInvitationCodeAddEdit($code, $UserGroupCode);
    }

    /**
     * @param PersonalizedInvitationCode $code
     * @param $Type
     * @param $UserGroupCode
     * @return FormAction
     */
    protected function PersonalizedInvitationCodeSaveProcess(PersonalizedInvitationCode $code, $Type)
    {
        $form = $this->formAction();
        $entityInput = $this->filter([
            'title' => 'str',
            'code' => 'str'
        ]);
        $LimitInput = $this->filter([
            'limit_use' => 'str',
            'limit_use_number' => 'int',
            'limit_time' => 'str',
            'limit_time_number' => 'datetime',
        ]);
        if($Type == 'Add')
        {
            if(empty($entityInput['code']))
            {
                $form->logError(\XF::phrase('please_enter_value_for_required_field_x', ['field' => \XF::phrase('xs_is_invitation_code')]));
            }
            $CodeInvitation = $this->finder('XenSoluce\InviteSystem:PersonalizedInvitationCode')->where('code', $entityInput['code'])->fetchOne();
            if(!empty($CodeInvitation))
            {
                $form->logError(\XF::phrase('xs_is_invitation_code_use'));
            }
            $entityInput['invitation_date'] = \XF::$time;
        }
        else
        {
            $entityInput['code'] = $code['code'];
        }
        if($LimitInput['limit_use'] == 'unlimited')
        {
            $entityInput['limit_use'] = -1;
        }
        elseif($LimitInput['limit_use'] == 'limited_use')
        {
            $entityInput['limit_use'] = $LimitInput['limit_use_number'];
        }
        if($LimitInput['limit_time']  == 'unlimited')
        {
            $entityInput['limit_time'] = -1;
        }
        elseif ($LimitInput['limit_time']  == 'limited_time')
        {
            $entityInput['limit_time'] = $LimitInput['limit_time_number'];
        }

        $form->basicEntitySave($code, $entityInput);

        return $form;
    }
    protected function finalizePersonalizedInvitationCode(PersonalizedInvitationCode $code, $UserGroupCode)
    {
        $UserGroupCode->code = $code->code;
        $UserGroupCode->entity_id = $code->ic_personalize_id;
        $userGroupCode = $this->filter([
            'type_user_group' => [
                'first_group' => 'int',
                'secondary_group' => 'int',
            ],
            'user_group' => [
                'first_user_group_id' => 'int',
                'secondary_user_group_id' => 'array-uint'
            ]
        ]);


        $UserGroupCode->max_invite = $code->limit_use;
        if($userGroupCode['type_user_group']['first_group'] && $userGroupCode['type_user_group']['secondary_group'])
        {
            $UserGroupCode->type_user_group = 'all';
        }
        elseif ($userGroupCode['type_user_group']['first_group'])
        {
            $UserGroupCode->type_user_group = 'first';
        }
        elseif ($userGroupCode['type_user_group']['secondary_group'])
        {
            $UserGroupCode->type_user_group = 'secondary';
        }
        $UserGroupCode->user_group = $userGroupCode['user_group']['first_user_group_id'];
        $UserGroupCode->secondary_user_group = $userGroupCode['user_group']['secondary_user_group_id'] ;
        $UserGroupCode->save();
    }

    public function actionPersonalizedInvitationCodeSave(ParameterBag $params)
    {
        $this->assertPostOnly();
        if($params->ic_personalize_id)
        {
            $code = $this->assertPersonalizedInvitationCodeExists($params->ic_personalize_id);
            $Type = 'Edit';
            $UserGroupCode = $this->finder('XenSoluce\InviteSystem:UserGroupCode')
                ->where('entity_id', '=', $code->ic_personalize_id)->fetchOne();
            if(empty($UserGroupCode))
            {
                $UserGroupCode = $this->em()->create('XenSoluce\InviteSystem:UserGroupCode');
            }
        }
        else
        {
            $code = $this->em()->create('XenSoluce\InviteSystem:PersonalizedInvitationCode');
            $Type = 'Add';
            $UserGroupCode = $this->em()->create('XenSoluce\InviteSystem:UserGroupCode');
        }

        $this->PersonalizedInvitationCodeSaveProcess($code, $Type)->run();
        if($this->filter('enable_add_user_group', 'int'))
        {
            $this->finalizePersonalizedInvitationCode($code, $UserGroupCode);
        }
        return $this->redirect($this->buildLink('invitation/personalized-invitation-code') . $this->buildLinkHash($code->ic_personalize_id));
    }
    public function actionPersonalizedInvitationCodeDelete(ParameterBag $params)
    {
        $code = $this->assertPersonalizedInvitationCodeExists($params->ic_personalize_id);
        if ($this->isPost())
        {
            $code->delete();

            return $this->redirect($this->buildLink('invitation/personalized-invitation-code') . $this->buildLinkHash('delete:' . $code->ic_personalize_id));
        }
        else
        {
            $viewParams = [
                'code' => $code
            ];
            return $this->view('XenSoluce\InviteSystem:Invitation\PersonalizeInvitationCode\Delete', 'xs_is_personalized_invitation_code_delete', $viewParams);
        }
    }
    public function actionPersonalizedInvitationCodeToggle()
    {
        $plugin = $this->plugin('XF:Toggle');

        return $plugin->actionToggle('XenSoluce\InviteSystem:PersonalizedInvitationCode', 'enable');
    }

    /**Banned users*/
    public function actionBanning(ParameterBag $params)
    {
        if ($params->user_id)
        {
            return $this->rerouteController(__CLASS__, 'BanningEdit', $params);
        }
        $banRepo = $this->getInviteBanRepo();
        $page = $this->filterPage();
        $perPage = 20;
        $Banning = $banRepo->findInvitationbanUserForList()->limitByPage($page, $perPage);
        $total = $Banning->total();

        $viewParams = [
            'Banning' => $Banning->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total
        ];
        return $this->view('XenSoluce\InviteSystem:Invitation\Banning\Listing', 'xs_is_banning_view', $viewParams);
    }

    protected function banningaddEdit(Banning $Ban, $addName = '')
    {
        $viewParams = [
            'Ban' => $Ban,
            'addName' => $addName
        ];

        return $this->view('XenSoluce\InviteSystem:Invitation\Tokens\Edit', 'xs_is_banning_edit', $viewParams);
    }

    public function actionBanningEdit(ParameterBag $params)
    {
        $Ban = $this->assertbanningExists($params['user_id']);
        return $this->banningaddEdit($Ban);
    }

    public function actionBanningAdd(ParameterBag $params)
    {
        if ($params['user_id'])
        {
            $user = $this->assertRecordExists('XF:User', $params['user_id']);
            $addName = $user->username;
        }
        else
        {
            $addName = '';
        }
        $Ban = $this->em()->create('XenSoluce\InviteSystem:Banning');
        return $this->banningaddEdit($Ban, $addName);
    }

    protected function BanningSaveProcess(Banning $Ban)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'username' => 'str',
            'ban_length' => 'str',
            'end_date' => 'datetime',
            'ban_reason' => 'str'
        ]);

        $user = $Ban->User;
        if (!$user)
        {
            $user = $this->finder('XF:User')->where('username', $input['username'])->fetchOne();
            if (!$user)
            {
                throw $this->exception($this->error(\XF::phrase('requested_user_not_found')));
            }
        }

        $form->apply(function(FormAction $form) use ($input, $user)
        {
            if ($input['ban_length'] == 'permanent')
            {
                $input['end_date'] = 0;
            }
            $error = "";

            $banRepo = $this->getInviteBanRepo();
            if (!$banRepo->InvitationbanUser($user, $input['end_date'], $input['ban_reason'], $error))
            {
                $form->logError($error);
            }
        });

        return $form;
    }

    public function actionBanningSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params->user_id)
        {
            $Ban = $this->assertbanningExists($params['user_id']);
        }
        else
        {
            $Ban = $this->em()->create('XenSoluce\InviteSystem:Banning');
        }
        $this->BanningSaveProcess($Ban)->run();

        return $this->redirect($this->buildLink('invitation/banning'));
    }

    public function actionBanningLift(ParameterBag $params)
    {
        $Ban = $this->assertbanningExists($params->user_id);

        if ($this->isPost())
        {
            $Ban->delete();
            return $this->redirect($this->buildLink('invitation/banning'));
        }
        else
        {
            $viewParams = [
                'Ban' => $Ban
            ];
            return $this->view('XenSoluce\InviteSystem:Invitation\Banning\Lift', 'xs_is_banning_lift', $viewParams);
        }
    }

    protected function getInviteBanRepo()
    {
        return $this->repository('XenSoluce\InviteSystem:Banning');
    }

    /**List code*/
    public function actionListCode(ParameterBag $params)
    {
        if ($params->code_id)
        {
            $code = $this->assertListCodeExists($params->code_id);
            $viewParams = [ 'code' => $code ];
            return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\View', 'xs_is_list_code_view', $viewParams);
        }
        else
        {
            $filters = $this->getListCodeFilterInput();
            $page = $this->filterPage();
            $perPage = 20;
            $codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->order('invitation_date', 'DESC');
            $user = $this->finder('XF:User');
            if(!empty($filters['user'])){
                $user = $this->finder('XF:User')->where('username', $filters['user'])->fetchOne();
            }

            $this->applyListCodeFilters($codes, $filters, $user);
            $codes->limitByPage($page, $perPage);
            $total = $codes->total();

            $viewParams = [
                'codes' => $codes->fetch(),
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'filters' => $filters,
                'user' => $user
            ];
            return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\Listing', 'xs_is_list_code', $viewParams);
        }
    }

    public function actionListCodeDelete(ParameterBag $params)
    {
        $code = $this->assertListCodeExists($params->code_id);
        if ($this->isPost())
        {
            $code->delete();

            return $this->redirect($this->buildLink('invitation/list-code') . $this->buildLinkHash('delete:' . $code->code));
        }
        else
        {
            $viewParams = [
                'code' => $code
            ];
            return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\Delete', 'xs_is_list_code_delete', $viewParams);
        }
    }

    protected function applyListCodeFilters($codes, array $filters, $user)
    {
        if(!empty($filters['user'])){
            $codes->where('user_id', $user->user_id);
        }
        if(empty($filters['no']))
        {
            if(!empty($filters['code_use'])){
                $codes->where('registered_user_id', '!=', '0');
            }
            if(!empty($filters['code_not_use'])){
                $codes->where('registered_user_id', '=', '0');
            }
        }
    }

    protected function getListCodeFilterInput()
    {
        $input = $this->filter([
            'user' => 'str',
            'code_use' => 'uint',
            'code_not_use' => 'uint'
        ]);
        $filters = [];
        if($input['user'])
        {
            $filters['user'] = $input['user'];
        }
        if($input['code_use'])
        {
            $filters['code_use'] = $input['code_use'];
        }
        if($input['code_not_use'])
        {
            $filters['code_not_use'] = $input['code_not_use'];
        }
        if($input['code_not_use'] && $input['code_use'])
        {
            $filters['no'] = 1;
        }
        return $filters;
    }

    public function actionListCodeFilters()
    {
        $filters = $this->getListCodeFilterInput();
        if ($this->filter('apply', 'bool'))
        {
            return $this->redirect($this->buildLink('invitation/list-code', '', $filters));
        }
        $user = "";
        if(!empty($filters['user'])){
            $user = $this->finder('XF:User')->where('username', $filters['user'])->fetchOne();
        }

        $viewParams = [
            'filters' => $filters,
            'user' => $user
        ];
        return $this->view('XenSoluce\InviteSystem:Invitation\ListCode\Filters', 'xs_is_list_code_filters', $viewParams);
    }

    /**
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\PrintableException|\XF\Mvc\Reply\Exception
     */
    public function actionSendEmail()
    {
        $this->setSectionContext('xsISSendEmail');
        if($this->isPost())
        {
            /** @var InvitationEmail $invitationEmailService */
            $invitationEmailService = $this->service('XenSoluce\InviteSystem:InvitationEmail', true);
            $invitationEmailService->setEmail($this->filter('emails', 'array'));
            $invitationEmailService->setVerifyEmail($this->filter('verify_email', 'bool'));
            $invitationEmailService->setSubject($this->filter('subject', 'str'));
            $invitationEmailService->setVerifySubject(true);

            if(!$invitationEmailService->validate($errors)) {
                throw $this->exception($this->error($errors));
            }

            $invitationEmailService->sendEmail();

            return $this->redirect($this->buildLink('invitation/send-email'));
        }
        return $this->view('', 'xs_is_send_email');

    }
    protected function assertTokenExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XenSoluce\InviteSystem:Token', $id, $with, $phraseKey);
    }
    protected function assertbanningExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XenSoluce\InviteSystem:Banning', $id, $with, $phraseKey);
    }
    protected function assertPersonalizedInvitationCodeExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XenSoluce\InviteSystem:PersonalizedInvitationCode', $id, $with, $phraseKey);
    }
    protected function assertListCodeExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XenSoluce\InviteSystem:CodeInvitation', $id, $with, $phraseKey);
    }
    protected function assertCodeUserGroupExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XenSoluce\InviteSystem:UserGroupCode', $id, $with, $phraseKey);
    }
}