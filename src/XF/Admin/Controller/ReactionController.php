<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\SortPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\Reaction;
use XF\Finder\ReactionFinder;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Repository\ReactionRepository;
use XF\Repository\StylePropertyRepository;
use XF\Util\Color;

use function intval;

class ReactionController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('reaction');
	}

	public function actionIndex()
	{
		$reactionRepo = $this->getReactionRepo();
		$reactionFinder = $reactionRepo->findReactionsForList();

		$viewParams = [
			'reactions' => $reactionFinder->fetch(),
		];
		return $this->view('XF:Reaction\List', 'reaction_list', $viewParams);
	}

	public function reactionAddEdit(Reaction $reaction)
	{
		$propertyRepo = $this->repository(StylePropertyRepository::class);
		$reactionRepo = $this->getReactionRepo();

		$viewParams = [
			'reaction' => $reaction,
			'colorData' => $propertyRepo->getStyleColorData(),
			'reactionScores' => $reactionRepo->getReactionScores(),
		];
		return $this->view('XF:Reaction\Edit', 'reaction_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$reaction = $this->assertReactionExists($params->reaction_id);
		return $this->reactionAddEdit($reaction);
	}

	public function actionAdd()
	{
		$reaction = $this->em()->create(Reaction::class);
		return $this->reactionAddEdit($reaction);
	}

	protected function reactionSaveProcess(Reaction $reaction)
	{
		$entityInput = $this->filter([
			'text_color' => 'str',
			'reaction_score' => 'int',
			'image_url' => 'str',
			'image_url_2x' => 'str',
			'emoji_shortname' => 'str',
			'sprite_mode' => 'uint',
			'sprite_params' => 'array',
			'display_order' => 'uint',
			'active' => 'bool',
		]);

		// If not in sprite mode, don't update the sprite params values. This can prevent a tedious
		// bit of data loss if the option is accidentally unselected.
		if (!$entityInput['sprite_mode'])
		{
			unset($entityInput['sprite_params']);
		}

		$form = $this->formAction();

		$customReactionScore = $this->filter('custom_reaction_score', 'int');
		if ($customReactionScore)
		{
			$entityInput['reaction_score'] = $customReactionScore;
		}
		else
		{
			$entityInput['reaction_score'] = intval($entityInput['reaction_score']);
		}

		$form->validate(function (FormAction $form) use ($entityInput)
		{
			if ($entityInput['text_color']
				&& strpos($entityInput['text_color'], '@xf-') !== 0
				&& !Color::isValidColor($entityInput['text_color'])
			)
			{
				$form->logError(\XF::phrase('please_choose_valid_text_color'));
			}
		});

		$form->basicEntitySave($reaction, $entityInput);

		$title = $this->filter('title', 'str');
		$form->validate(function (FormAction $form) use ($title)
		{
			if ($title === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});
		$form->apply(function () use ($reaction, $title)
		{
			$masterTitle = $reaction->getMasterPhrase();
			$masterTitle->phrase_text = $title;
			$masterTitle->save();
		});

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->reaction_id)
		{
			$reaction = $this->assertReactionExists($params['reaction_id']);
		}
		else
		{
			$reaction = $this->em()->create(Reaction::class);
		}

		$this->reactionSaveProcess($reaction)->run();

		return $this->redirect($this->buildLink('reactions'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$reaction = $this->assertReactionExists($params->reaction_id);
		if (!$reaction->canDelete($error))
		{
			return $this->error($error);
		}

		if ($this->isPost())
		{
			$reaction->delete();
			return $this->redirect($this->buildLink('reactions'));
		}
		else
		{
			$viewParams = [
				'reaction' => $reaction,
			];
			return $this->view('XF:Reaction\Delete', 'reaction_delete', $viewParams);
		}
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(ReactionFinder::class);
	}

	public function actionSort()
	{
		$reactionRepo = $this->getReactionRepo();
		$reactionFinder = $reactionRepo->findReactionsForList();
		$reactions = $reactionFinder->fetch();

		if ($this->isPost())
		{
			$sortData = $this->filter('reactions', 'json-array');

			/** @var SortPlugin $sorter */
			$sorter = $this->plugin(SortPlugin::class);
			$sorter->sortFlat($sortData, $reactions);

			return $this->redirect($this->buildLink('reactions'));
		}
		else
		{
			$viewParams = [
				'reactions' => $reactions,
			];
			return $this->view('XF:Reaction\Sort', 'reaction_sort', $viewParams);
		}
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Reaction
	 */
	protected function assertReactionExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(Reaction::class, $id, $with, $phraseKey);
	}

	/**
	 * @return ReactionRepository
	 */
	protected function getReactionRepo()
	{
		return $this->repository(ReactionRepository::class);
	}
}
