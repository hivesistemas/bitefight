<?php
/**
 * Created by PhpStorm.
 * User: Osein
 * Date: 1/25/2018
 * Time: 9:01 PM
 */

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use Illuminate\Support\Facades\DB;

class HuntController extends Controller
{

	public function __construct()
	{
		$this->middleware('auth');
	}

	public function getHunt()
	{
		$activity = DB::table('user_activity')
			->where('user_id', user()->getId())
			->where('activity_type', ACTIVITY_TYPE_GRAVEYARD)
			->count();

		if($activity && $activity->end_time > time()) {
			return redirect(url('/city/graveyard'));
		}

		return view('user.hunt', [
			'hunt1Chance' => $this->getHuntChance(1),
			'hunt2Chance' => $this->getHuntChance(2),
			'hunt3Chance' => $this->getHuntChance(3),
			'hunt4Chance' => $this->getHuntChance(4),
			'hunt5Chance' => $this->getHuntChance(5),
			'hunt1Exp' => $this->getHuntExp(1),
			'hunt2Exp' => $this->getHuntExp(2),
			'hunt3Exp' => $this->getHuntExp(3),
			'hunt4Exp' => $this->getHuntExp(4),
			'hunt5Exp' => $this->getHuntExp(5),
			// Todo: Each hunt reward queries 3 times. Optimise
			'hunt1Reward' => $this->getHuntReward(1),
			'hunt2Reward' => $this->getHuntReward(2),
			'hunt3Reward' => $this->getHuntReward(3),
			'hunt4Reward' => $this->getHuntReward(4),
			'hunt5Reward' => $this->getHuntReward(5),
		]);
	}

	/**
	 * @param int $huntNo
	 * @return int
	 */
	public function getHuntReward($huntNo)
	{
		$user_level = getLevel(user()->getExp());
		$userTalentCha = DB::table('user_talents')
			->join('talents', 'talents.id', '=', 'user_talents.talent_id')
			->select(DB::raw('SUM(talents.cha) as totalTalentCha'))
			->where('user_talents.user_id', user()->getId())
			->first();
		$userItemCha = DB::table('user_items')
			->join('items', 'items.id', '=', 'user_items.item_id')
			->select(DB::raw('SUM(items.cha) as totalItemCha'))
			->whereRaw('user_items.user_id = ? AND 
					((items.model = 2 AND user_items.expire > ?) OR (items.model != 2 AND user_items.equipped = 1))',
				[user()->getId(), time()])
			->first();
		$serendipity = DB::table('user_items')
			->where('user_id', user()->getId())
			->where('item_id', 156)
			->where('expire', '>', time())
			->count();

		$userTotalCha = $userTalentCha->totalTalentCha + $userItemCha->totalItemCha + user()->getCha();

		if ($huntNo == 1) {
			$reward = ($userTotalCha * 2) + ($user_level * 1) + 450;
		} elseif ($huntNo == 2) {
			$reward = ($userTotalCha * 3) + ($user_level * 2) + 540;
		} elseif ($huntNo == 3) {
			$reward = ($userTotalCha * 3) + ($user_level * 3) + 609;
		} elseif ($huntNo == 4) {
			$reward = ($userTotalCha * 4) + ($user_level * 4) + 714;
		} else {
			$reward = ($userTotalCha * 5) + ($user_level * 5) + 860;
		}

		if($serendipity) {
			$reward *= 2;
		}

		return $reward;
	}

	/**
	 * @param int $huntNo
	 * @return int
	 */
	public function getHuntExp($huntNo)
	{
		$user_level = getLevel(user()->getExp());
		return ($huntNo+(ceil(pow($user_level, 0.1*$huntNo))));
	}

	/**
	 * @param int $huntNo
	 * @return float
	 */
	public function getHuntChance($huntNo)
	{
		$user_level = getLevel(user()->getExp());

		if ($huntNo == 1) {
			if ($user_level < 75) {
				return floor(($user_level*0.2)+75);
			} elseif ($user_level > 74 && $user_level < 165) {
				return floor(($user_level*0.1)+82.5);
			} else {
				return 99;
			}
		} elseif ($huntNo == 2) {
			if ($user_level < 125) {
				return floor(($user_level*0.2)+47);
			} elseif ($user_level > 124 && $user_level < 289) {
				return floor(($user_level*0.083)+72);
			} else {
				return 96;
			}
		} elseif ($huntNo == 3) {
			if ($user_level < 225) {
				return floor(($user_level*0.15)+32);
			} elseif ($user_level > 224 && $user_level < 420) {
				return floor(($user_level*0.065)+65.75);
			} else {
				return 93;
			}
		} elseif ($huntNo == 4) {
			if ($user_level < 350) {
				return floor(($user_level*0.09)+31);
			} elseif ($user_level > 349 && $user_level < 600) {
				return floor(($user_level*0.046)+62.5);
			} else {
				return 90;
			}
		} else {
			if ($user_level < 550) {
				return floor(($user_level*0.06)+21);
			} elseif ($user_level > 499 && $user_level < 850) {
				return floor(($user_level*0.037)+54);
			} else {
				return 85;
			}
		}
	}

	public function postHumanHunt($huntId)
	{
		if($huntId < 1 || $huntId > 5) {
			throw new InvalidRequestException();
		}

		$requiredAp = ceil($huntId / 2);

		if (user()->getApNow() < $requiredAp) {
			throw new InvalidRequestException();
		}

		$activity = DB::table('user_activity')
			->where('user_id', user()->getId())
			->where('activity_type', ACTIVITY_TYPE_GRAVEYARD)
			->count();

		if($activity && $activity->end_time > time()) {
			throw new InvalidRequestException();
		}

		user()->setApNow(user()->getApNow() - $requiredAp);
		$rewardExp = $this->getHuntExp($huntId);
		$rewardGold = $this->getHuntReward($huntId);
		$huntChance = $this->getHuntChance($huntId);
		$rand = rand(1, 100);
		$fragmentReward = 0;
		$success = false;

		if ($rand <= $huntChance) {
			user()->processExpIfLevelUp($rewardExp);

			user()->gold += $rewardGold;
			user()->s_booty += $rewardGold;

			/*$missions = ORM::for_table('user_mission')
				->where_raw('(user_id = ? AND type = ?) AND (special = ? OR special = ?)', [$this->user->id, UserMission::TYPE_HUMAN_HUNT, $id, 0])
				->where('accepted', 1)
				->where_raw('progress < count')
				->where('status', 0)
				->find_many();
			$time = time();

			foreach($missions as $mission) {
				if($mission->time > 0) {
					if($mission->accepted_time + 3600 * $mission->time > $time) {
						$mission->status = 2;
						$mission->save();
					}
				} else {
					$mission->progress++;
					$mission->save();

					if($mission->progress == $mission->count) {
						$missionObj = new StdClass;
						$missionObj->type = $mission->type;
						$missionObj->count = $mission->count;
						if($this->session->has('completedMissions')) {
							$completedMissions = $this->session->get('completedMissions');
							$completedMissions[] = $missionObj;
							$this->session->set('completedMissions', $completedMissions);
						} else {
							$completedMissions = [$missionObj];
							$this->session->set('completedMissions', $completedMissions);
						}
					}
				}
			}*/

			if ($rand < 4) {
				user()->fragment++;
				$fragmentReward++;
			}

			$success = true;
		}

		return view('user.hunt_human_result', [
			'success' => $success,
			'huntId' => $huntId,
			'rewardExp' => $rewardExp,
			'rewardGold' => $rewardGold,
			'fragmentReward' => $fragmentReward
		]);
	}

}