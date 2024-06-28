<?php

namespace App\Http\Controllers;

use App\Events\GroupMessage;
use App\Models\Group;
use App\Models\GroupChat;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $user_id = auth()->user()->id;
        $group_ids = GroupMember::where('user_id', $user_id)->pluck('group_id')->toArray();
        $additional_group_ids = Group::where('user_id', $request->user_id)->pluck('id')->toArray();
        $group_ids = array_merge($group_ids, $additional_group_ids);
        $groups = Group::whereIn('id', $group_ids)->get();
        return response()->json(['status'=>'success','data'=> $groups]);
    }
    public function store(Request $request)
    {
        $group = new Group();
        $group->name = $request->title;
        $group->member_limit = $request->limit;
        $group->user_id = auth()->user()->id;
        $group->status = 1;
        if($request->image && $request->image != 'undefined'){
            $img = $request->file('image');
            $ext = rand().".".$img->getClientOriginalName();
            $img->move("group_images/",$ext);
            $group->image = $ext;
        }
        $group->save();
        return response()->json(['status'=>'succuss','data'=>$group]);
    }
    public function search_member(Request $request)
    {
        $find_members = GroupMember::where('group_id', $request->group_id)->pluck('user_id')->toArray();
        $find_members[] = auth()->user()->id;
        $members = User::where('name','LIKE','%'.$request->value.'%')->whereNotIn('id',$find_members)->get();
        return response()->json(['status'=>'success','data'=> $members]);
    }

    public function add_member(Request $request)
    {
        $member = new GroupMember();
        $member->user_id = $request->user_id;
        $member->group_id = $request->group_id;
        $member->save();
        
        $new_member = User::find($request->user_id);
        $chat = new GroupChat();
        $chat->user_id = $request->user_id;
        $chat->group_id = $request->group_id;
        $chat->read_ = false;
        $chat->message = auth()->user()->name.' added '. $new_member->name .' in the group.';
        $chat->type = 'alert';
        $chat->save();
        $sender =auth()->user();
        event(new GroupMessage($chat,$sender));
        return response()->json(['status'=>'success','data'=>$member]);
    }
    public function load_group_chats(Request $request)
    {
        $chats = GroupChat::with('user')->where('group_id',$request->group_id)->get();
        $group_info = Group::find($request->group_id);
        return response()->json(['status'=>'success','data'=>$chats,'group_info'=>$group_info]);
    }
    public function members(Request $request)
    {
        $members = GroupMember::with('user')->where('group_id',$request->group_id)->get();
        $group = Group::find($request->group_id);
        $group_admin = User::find($group->user_id); 
        return response()->json(['status'=>'success','data'=>$members,'group_admin'=>$group_admin,'group'=>$group]);
    }
    public function leave_group(Request $request)
    {
        $check_admin = Group::where('id',$request->group_id)->where('user_id',$request->user_id)->count();
        if($check_admin > 0)
        {
            return response()->json(['status'=>'error','msg'=>'You are admin']);
        }
        $leave = GroupMember::where('group_id',$request->group_id)->where('user_id',$request->user_id)->delete();
        $chat = new GroupChat();
        $chat->user_id = $request->user_id;
        $chat->group_id = $request->group_id;
        $chat->read_ = false;
        $chat->message = auth()->user()->name.' leave the group.';
        $chat->type = 'alert';
        $chat->save();
        $sender =auth()->user();
        event(new GroupMessage($chat,$sender));
        return response()->json(['status'=>'success','msg'=>'Group Leaved']);
    }
    public function kick_member(Request $request)
    {
        $member = User::find($request->user_id);
        $leave = GroupMember::where('group_id',$request->group_id)->where('user_id',$request->user_id)->delete();
        $chat = new GroupChat();
        $chat->user_id = $request->user_id;
        $chat->group_id = $request->group_id;
        $chat->read_ = false;
        $chat->message = auth()->user()->name.'Kicked '.$member->name.' out of the group.';
        $chat->type = 'alert';
        $chat->save();
        $sender =auth()->user();
        event(new GroupMessage($chat,$sender));
        return response()->json(['status'=>'success','msg'=>'Group Leaved']);
    }

}
