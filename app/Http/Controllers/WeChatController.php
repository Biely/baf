<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class WeChatController extends Controller
{

    /**
     * 处理微信的请求消息
     *
     * @return string
     */
    public function serve()
    {
        Log::info('request arrived.'); # 注意：Log 为 Laravel 组件，所以它记的日志去 Laravel 日志看，而不是 EasyWeChat 日志
        $app = app('wechat.official_account');
        $app->server->push(function ($message){
            $keyword = trim($message['Content']);
            $openid = $message['FromUserName'];
            if($keyword == '告白'){
                $step = $this->getstep($openid);
                $content = $this->rnews($step,$keyword,$openid);   
            }else{
                $step = Redis::get($openid);
                if($step){
                    $content = $this->rnews($step,$keyword,$openid);
                }else{
                    exit;
                }
            }
            return $content;
        });
        return $app->server->serve();
    }

    public function getstep($openid){
        $flag = Redis::setnx($openid, 1);
        if($flag){
            $flag = 1;
        }else{
            $flag = Redis::get($openid);
        }
        return $flag;
    }

    public function rnews($step,$keyword = "",$openid = ""){
        //$redis = new Redis;
        if($step == 1){
            $content = "很高兴见到你。\n\n欢迎开始你的零风险告白。\n\n第一步，你需要先告诉小概【你的真实姓名】\n\n请提供准确无误的真实姓名，不要提供昵称、网名、英文名等）";
            Redis::incr($openid); 
        }
        if($step == 2){
            if($keyword == '1'){
                $name = $this->getkeyword($openid);
                if($name == null){
                    $content = "很高兴见到你。\n\n欢迎开始你的零风险告白。\n\n第一步，你需要先告诉小概【你的真实姓名】\n\n请提供准确无误的真实姓名，不要提供昵称、网名、英文名等）";
                }else{
                    $flag = DB::table('loves')->insert(['openid' => $openid, 'name' => $name]);
                    if($flag){
                        $content = "接下来，请告诉小概【ta 的真实姓名】\n\n（请提供准确无误的真实姓名，不要提供昵称、网名、英文名等）";
                        Redis::incr($openid);
                        Redis::del("keyword:".$openid);
                    }else{
                        $content = "error02,请重新输入";
                    }
                }  
            }else if($keyword == '2'){
                $content = "请将修改好的【你的真实姓名】\n发送给小概~";
                Redis::del("keyword:".$openid);
            }else{
                $content = "你的名字是：\n".$keyword."\n\n回复【1】确认\n回复【2】修改\n-------------------------\n注意：确认了之后就没法再修改了哦~";
                $this->setkeyword($openid,$keyword);
            }
        }
        if($step == 3){
            if($keyword == '1'){
                $love = $this->getkeyword($openid);
                if($love == null){
                    $content = "接下来，请告诉小概【ta 的真实姓名】\n\n（请提供准确无误的真实姓名，不要提供昵称、网名、英文名等）";
                }else{
                    $flag = DB::table('loves')->where('openid', $openid)->update(['love' => $love]);
                    if($flag){
                        $content = "接下来，请告诉小概你的【表白内容】哦~\n\n如果对方也填写了你的名字，对方就会收到这段话。";
                        Redis::incr($openid);
                        Redis::del("keyword:".$openid);
                    }else{
                        $content = "error03,请重新输入";
                    }
                }  
            }else if($keyword == '2'){
                $content = "请将修改好的【ta的真实姓名】\n发送给小概~";
                Redis::del("keyword:".$openid);
            }else{
                $content = "ta的名字是：\n".$keyword."\n\n回复【1】确认\n回复【2】修改\n-------------------------\n注意：确认了之后就没法再修改了哦~";
                $this->setkeyword($openid,$keyword);
            }
        }
        if($step == 4){
            if($keyword == '1'){
                $word = $this->getkeyword($openid);
                if($word == null){
                    $content = "接下来，请告诉小概你的【表白内容】哦~\n\n如果对方也填写了你的名字，对方就会收到这段话。";
                }else{
                    $flag = DB::table('loves')->where('openid', $openid)->update(['word' => $word]);
                    if($flag){
                        $content = "请告诉小概你的【手机号】哦~\n\n如果配对成功的话，小概会将你的联系方式告诉对方。";
                        Redis::incr($openid);
                        Redis::del("keyword:".$openid);
                    }else{
                        $content = "error04,请重新输入";
                    }
                }  
            }else if($keyword == '2'){
                $content = "请将修改好的【表白内容】\n发送给小概~";
                Redis::del("keyword:".$openid);
            }else{
                $content = "你想对ta说的话是：\n".$keyword."\n\n回复【1】确认\n回复【2】修改\n-------------------------\n注意：确认了之后就没法再修改了哦~";
                $this->setkeyword($openid,$keyword);
            }
        }
        if($step == 5){
            if($keyword == '1'){
                $tel = $this->getkeyword($openid);
                if($tel == null){
                    $content = "请告诉小概你的【手机号】哦~\n\n如果配对成功的话，小概会将你的联系方式告诉对方。";
                }else{
                    $flag = DB::table('loves')->where('openid', $openid)->update(['tel' => $tel]);
                    if($flag){
                        $content = '你的告白，小概收到啦。'."\n\n".'最后——'."\n\n".'请把活动推送<a href="https://mp.weixin.qq.com/s/ESEkgGnNOdEsm_MIQdPXhA">“敢不敢赌我喜欢你？”</a>发到ta能看见的地方（朋友圈，群聊之类的），这样ta才有机会来填写你的名字~'."\n\n".'在 11月14日 24点前，你可以随时来这回复【结果】，查看ta是否也填了你的名字'."\n\n".'很高兴你来做了这次尝试。'."\n\n".'不管怎样，在11月14日24点前，等一等吧。'."\n\n".'小概陪你。';
                        Redis::incr($openid);
                        Redis::del("keyword:".$openid);
                    }else{
                        $content = "error05,请重新输入";
                    }
                }  
            }else if($keyword == '2'){
                $content = "请将修改好的【手机号】\n发送给小概~";
                $redis->del("keyword:".$openid);
            }else{
                $content = "你手机号是：\n".$keyword."\n\n回复【1】确认\n回复【2】修改\n-------------------------\n注意：确认了之后就没法再修改了哦~";
                $this->setkeyword($openid, $keyword);
            }
        }
        if($step>5){
            $content =  '你的告白，小概收到啦。'."\n\n".'最后——'."\n\n".'请把活动推送<a href="https://mp.weixin.qq.com/s/ESEkgGnNOdEsm_MIQdPXhA">“敢不敢赌我喜欢你？”</a>发到ta能看见的地方（朋友圈，群聊之类的），这样ta才有机会来填写你的名字~'."\n\n".'在 11月14日 24点前，你可以随时来这回复【结果】，查看ta是否也填了你的名字'."\n\n".'很高兴你来做了这次尝试。'."\n\n".'不管怎样，在11月14日24点前，等一等吧。'."\n\n".'小概陪你。';
        }
        return $content;
    }

    public function setkeyword($openid,$keyword){
        //$redis = new Redis;
        Redis::set('keyword:'.$openid,$keyword);
        return $keyword;
    }

    public function getkeyword($openid){
        //$redis = new Redis;
        $keyword = Redis::get('keyword:'.$openid);
        return $keyword;
    }

    public function rush(){
        return Redis::flushdb();
    }
}