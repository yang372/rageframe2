<?php
namespace common\models\wechat;

use Yii;
use common\models\member\MemberInfo;
use common\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%wechat_fans}}".
 *
 * @property string $id
 * @property string $member_id 用户id
 * @property string $unionid 唯一公众号ID
 * @property string $openid openid
 * @property string $nickname 昵称
 * @property string $head_portrait 头像
 * @property int $sex 性别
 * @property int $follow 是否关注[1:关注;0:取消关注]
 * @property string $followtime 关注时间
 * @property string $unfollowtime 取消关注时间
 * @property int $group_id 分组id
 * @property string $tag 标签
 * @property string $last_longitude 最后一次经纬度上报
 * @property string $last_latitude 最后一次经纬度上报
 * @property string $last_address 最后一次经纬度上报地址
 * @property int $last_updated 最后更新时间
 * @property string $country 国家
 * @property string $province 省
 * @property string $city 市
 * @property int $status 状态[-1:删除;0:禁用;1启用]
 * @property string $created_at 添加时间
 * @property string $updated_at 修改时间
 */
class Fans extends \common\models\common\BaseModel
{
    const FOLLOW_ON = 1;
    const FOLLOW_OFF = -1;

    /**
     * 关注状态
     *
     * @var array
     */
    public static $followStatus = [
        self::FOLLOW_ON  => '已关注',
        self::FOLLOW_OFF => '未关注',
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%wechat_fans}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['member_id', 'sex', 'follow', 'followtime', 'unfollowtime', 'group_id', 'last_updated', 'status', 'created_at', 'updated_at'], 'integer'],
            [['unionid'], 'string', 'max' => 64],
            [['openid', 'nickname'], 'string', 'max' => 50],
            [['head_portrait'], 'string', 'max' => 150],
            [['tag'], 'string', 'max' => 1000],
            [['last_longitude', 'last_latitude'], 'string', 'max' => 10],
            [['last_address', 'country', 'province', 'city'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'member_id' => 'Member ID',
            'unionid' => 'Unionid',
            'openid' => 'Openid',
            'nickname' => '昵称',
            'head_portrait' => '头像',
            'sex' => '性别',
            'follow' => '关注状态',
            'followtime' => '关注时间',
            'unfollowtime' => '取消关注时间',
            'group_id' => 'Group ID',
            'tag' => '标签',
            'last_longitude' => 'Last Longitude',
            'last_latitude' => 'Last Latitude',
            'last_address' => 'Last Address',
            'last_updated' => 'Last Updated',
            'country' => '国家',
            'province' => '省',
            'city' => '市',
            'status' => '状态',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }

    /**
     * @param $openid
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function follow($openid)
    {
        // 获取用户信息
        $user = Yii::$app->wechat->app->user->get($openid);
        $user = ArrayHelper::toArray($user);

        $fans = static::findModel($openid);
        $fans->attributes = $user;
        $fans->group_id = $user['groupid'];
        $fans->followtime = $user['subscribe_time'];
        $fans->follow = self::FOLLOW_ON;
        $fans->save();

         FansStat::upFollowNum();
    }

    /**
     * 取消关注
     *
     * @param $openid
     */
    public static function unFollow($openid)
    {
        if ($fans = self::findOne(['openid' => $openid]))
        {
            $fans->follow = self::FOLLOW_OFF;
            $fans->unfollowtime = time();
            $fans->save();

            FansStat::upUnFollowNum();
        }
    }

    /**
     * 同步关注的用户信息
     *
     * @param $openid
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \yii\db\Exception
     */
    public static function sync($openid)
    {
        $app = Yii::$app->wechat->app;
        $user = $app->user->get($openid);

        if ($user['subscribe'] == self::FOLLOW_ON)
        {
            $fans = static::findModel($openid);
            $fans->attributes = $user;
            $fans->group_id = $user['groupid'];
            $fans->head_portrait = $user['headimgurl'];
            $fans->followtime = $user['subscribe_time'];
            $fans->follow = self::FOLLOW_ON;
            $fans->save();

            // 同步标签
            $labelData = [];
            foreach ($user['tagid_list'] as $tag)
            {
                $labelData[] = [$fans->id, $tag];
            }

            FansTagMap::add($fans->id, $labelData);
        }
    }

    /**
     * 获取关注的人数
     *
     * @return int|string
     */
    public static function getCountFollowFans()
    {
        return self::find()->where(['follow' => self::FOLLOW_ON])->select(['follow'])->count();
    }

    /**
     * 获取用户信息
     *
     * @param $openid
     * @return array|Fans|null|\yii\db\ActiveRecord
     */
    protected static function findModel($openid)
    {
        if (empty($openid) || empty(($model = self::find()->where(['openid' => $openid])->one())))
        {
            return new self();
        }

        return $model;
    }

    /**
     * 关联会员
     */
    public function getMember()
    {
        return $this->hasOne(MemberInfo::class, ['id' => 'member_id']);
    }

    /**
     * 标签关联
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTags()
    {
        return $this->hasMany(FansTagMap::class,['fans_id' => 'id']);
    }
}
