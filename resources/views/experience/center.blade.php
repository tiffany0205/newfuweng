@extends('layouts.app')
@section('title','幸运中心')
@section('content')
<div class="experience-page">
  <section class="experience-hero">
    <div><span class="eyebrow">PLAYER JOURNEY</span><h1>幸运中心</h1><p>任务、成长、收藏和奖励都在这里</p></div>
    <div class="season-card"><span>当前章节</span><b>{{ $season[0] }}</b><div><i style="width:{{ min(100,$state->completed_laps/$season[1]*100) }}%"></i></div><small>{{ $state->completed_laps }} / {{ $season[1] }} 圈</small></div>
    <div class="hero-actions"><a href="{{ route('help.index') }}">玩法与地标</a><a class="back-game" href="{{ route('game.index') }}">返回棋盘 →</a></div>
  </section>

  <nav class="center-nav">
    <a href="#tasks">任务</a><a href="#checkin">签到</a><a href="#milestones">里程碑</a><a href="#invites">邀请</a><a href="#collection">收藏</a><a href="#prizes">奖池</a><a href="#rankings">榜单</a><a href="#messages">消息</a>
  </nav>

  <section id="tasks" class="feature-section"><div class="section-heading"><div><span>01</span><h2>任务中心</h2></div><p>完成任务后手动领取奖励</p></div>
    <div class="feature-grid task-grid">@foreach($tasks as $task) @php $percent=min(100,$task->progress_value/$task->target*100); @endphp
      <article class="feature-card"><div class="card-icon">{{ $task->period==='weekly'?'🗓️':'⚡' }}</div><div class="card-body"><div class="card-title"><b>{{ $task->name }}</b><em>{{ $task->period==='weekly'?'每周':'每日' }}</em></div><p>{{ $task->description }}</p><div class="progress"><i style="width:{{ $percent }}%"></i></div><small>{{ min($task->progress_value,$task->target) }} / {{ $task->target }} · 奖励 {{ $task->reward_value }} {{ $task->reward_type==='chance'?'次机会':'份道具' }}</small></div>
      @if($task->claimed)<span class="state-done">已领取</span>@elseif($task->progress_value >= $task->target)<form method="post" action="{{ route('experience.tasks.claim',$task->id) }}">@csrf<button>领取</button></form>@else<span class="state-wait">进行中</span>@endif
      </article>@endforeach</div>
  </section>

  <section id="checkin" class="feature-section"><div class="section-heading"><div><span>02</span><h2>14 天签到旅程</h2></div><p>第 7、14 天获得双倍机会</p></div>
    <div class="checkin-calendar">@for($day=1;$day<=14;$day++) @php $signed=$checkins->contains(fn($c)=>$c->streak_day===$day); @endphp <div class="checkin-day {{ $signed?'signed':'' }} {{ in_array($day,[7,14])?'bonus':'' }}"><span>DAY {{ $day }}</span><b>{{ in_array($day,[7,14])?'🎁':'🎲' }}</b><small>{{ in_array($day,[7,14])?'10 次':'5 次' }}</small><i>{{ $signed?'✓':'' }}</i></div>@endfor</div>
  </section>

  <section id="milestones" class="feature-section"><div class="section-heading"><div><span>03</span><h2>圈数宝箱</h2></div><p>每一次绕行都有阶段回报</p></div>
    <div class="milestone-track">@foreach($milestones as $m) @php $claimed=in_array($m->id,$claimedMilestones);$ready=$state->completed_laps >= $m->required_laps; @endphp <article class="milestone {{ $ready?'ready':'' }}"><div class="chest">{{ $claimed?'✅':($ready?'🎁':'🔒') }}</div><b>{{ $m->required_laps }} 圈</b><span>{{ $m->name }}</span><small>{{ $m->reward_label }}</small>@if($claimed)<em>已领取</em>@elseif($ready)<form method="post" action="{{ route('experience.milestones.claim',$m->id) }}">@csrf<button>开启</button></form>@endif</article>@endforeach</div>
  </section>

  <section id="invites" class="feature-section"><div class="section-heading"><div><span>04</span><h2>邀请好友中心</h2></div><button class="copy-premium" data-copy="{{ route('register',['invite'=>auth()->user()->invite_code]) }}">复制专属链接</button></div>
    <div class="invite-summary"><div><b>{{ $invites->count() }}</b><span>成功注册</span></div><div><b>{{ $invites->where('recharge_awarded',true)->count() }}</b><span>首充达标</span></div><div><b>{{ $invites->count()*5+$invites->where('recharge_awarded',true)->count()*10 }}</b><span>累计机会</span></div><div><b>{{ auth()->user()->invite_code }}</b><span>我的邀请码</span></div></div>
    <div class="table-wrap"><table><thead><tr><th>好友</th><th>注册时间</th><th>充值进度</th><th>注册奖励</th><th>首充奖励</th></tr></thead><tbody>@forelse($invites as $friend)<tr><td>{{ mb_substr($friend->name,0,1) }}***</td><td>{{ $friend->created_at }}</td><td>{{ number_format(min(1000,$friend->recharge_cents??0)/100,2) }} / 10U</td><td>✅ 已获得</td><td>{{ $friend->recharge_awarded?'✅ 已获得':'⏳ 待达标' }}</td></tr>@empty<tr><td colspan="5">还没有邀请记录，分享链接开启组队旅程吧</td></tr>@endforelse</tbody></table></div>
  </section>

  <section id="collection" class="feature-section"><div class="section-heading"><div><span>05</span><h2>背包与收藏</h2></div><p>道具带来策略，皮肤只改变外观不影响公平</p></div>
    <h3 class="sub-title">道具背包</h3><div class="collection-grid">@foreach($items as $item)<article class="collect-card"><b>{{ $item->icon }}</b><div><strong>{{ $item->name }}</strong><p>{{ $item->description }}</p></div><span>× {{ $item->quantity }}</span>@if($item->quantity>0&&$item->usable)<form method="post" action="{{ route('experience.items.use',$item->id) }}">@csrf<button>使用</button></form>@endif</article>@endforeach</div>
    <h3 class="sub-title">成就徽章</h3><div class="collection-grid">@foreach($achievements as $achievement)<article class="collect-card {{ $achievement->unlocked_at?'unlocked':'locked' }}"><b>{{ $achievement->icon }}</b><div><strong>{{ $achievement->name }}</strong><p>{{ $achievement->description }}</p></div><span>{{ $achievement->unlocked_at?'已解锁':'未解锁' }}</span></article>@endforeach</div>
    <h3 class="sub-title">棋子皮肤</h3><div class="skin-row">@foreach($skins as $skin)<article class="skin-card {{ auth()->user()->equipped_skin_id===$skin->id?'equipped':'' }}"><b>{{ $skin->icon }}</b><span>{{ $skin->name }}</span><small>{{ strtoupper($skin->rarity) }}</small>@if($skin->unlocked_at)<form method="post" action="{{ route('experience.skins.equip',$skin->id) }}">@csrf<button>{{ auth()->user()->equipped_skin_id===$skin->id?'使用中':'装备' }}</button></form>@else<em>🔒</em>@endif</article>@endforeach</div>
  </section>

  <section id="prizes" class="feature-section"><div class="section-heading"><div><span>06</span><h2>奖池与领奖中心</h2></div><p>中奖、提交资料、审核和发放全程可追踪</p></div>
    <div class="prize-pool">@foreach($prizePool as $prize)<div><b>{{ $prize->icon }}</b><span>{{ $prize->label }}</span></div>@endforeach</div>
    <div class="prize-columns"><div><h3>实时中奖播报</h3>@forelse($winners as $winner)<p class="winner-feed"><span>{{ mb_substr($winner->name,0,1) }}***</span>获得 <b>{{ $winner->prize_name }}</b><time>{{ $winner->created_at }}</time></p>@empty<p class="empty">等待第一位幸运玩家</p>@endforelse</div><div><h3>我的领奖记录</h3>@forelse($claims as $claim)<div class="claim-card"><div><b>{{ $claim->prize_name }}</b><span>{{ $claim->claim_status??$claim->status }}</span></div>@if(!in_array($claim->status,['issued','submitted']))<form method="post" action="{{ route('experience.winnings.claim',$claim->id) }}">@csrf<select name="method"><option value="wallet">数字钱包</option><option value="bank">银行/本地支付</option><option value="delivery">实物配送</option></select><input name="details" required placeholder="钱包地址、账户或收货信息"><button>提交领奖资料</button></form>@endif</div>@empty<p class="empty">中奖后将在这里领取</p>@endforelse</div></div>
  </section>

  <section id="rankings" class="feature-section"><div class="section-heading"><div><span>07</span><h2>多维排行榜</h2></div><p>总进度、每日活跃和邀请贡献</p></div><x-ranking-rewards variant="full" /><div class="ranking-columns">@foreach(['progress'=>'总进度榜','daily'=>'今日活跃榜','invite'=>'邀请榜'] as $key=>$label)<div><h3>{{ $label }}</h3><ol>@forelse($rankings[$key] as $rank=>$player)<li><em>{{ $rank+1 }}</em><span>{{ mb_substr($player->name,0,1) }}***</span><b>{{ $key==='progress'?$player->completed_laps.'圈 '.($player->current_position+1).'格':$player->score }}</b></li>@empty<li>暂无数据</li>@endforelse</ol></div>@endforeach</div></section>

  <section id="messages" class="feature-section"><div class="section-heading"><div><span>08</span><h2>消息中心</h2></div><form method="post" action="{{ route('experience.messages.read') }}">@csrf<button>全部已读</button></form></div><div class="message-list">@forelse($messages as $message)<article class="{{ $message->read_at?'':'unread' }}"><i></i><div><b>{{ $message->title }}</b><p>{{ $message->content }}</p></div><time>{{ $message->created_at }}</time></article>@empty<p class="empty">暂无消息</p>@endforelse</div></section>
</div>
@endsection
