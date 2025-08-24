@extends('layouts.email')

@section('body')
    <!-- content -->
    <td valign="top" class="bodyContent" mc:edit="body_content">
        <p>{!!$msg!!}</p>
        <a class="btn" href="{{env('APP_URL')}}/login">Login to your Account to view</a>
        <p>If you have any questions or need assistance getting started, please don't hesitate to reach out.</p>
    </td>
@endsection
