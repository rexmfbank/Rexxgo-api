@extends('layouts.email')

@section('body')
    <!-- content -->
    <td valign="top" class="bodyContent" mc:edit="body_content">
        <p>{!!$msg!!}</p>
        <p>If you have any questions or need assistance getting started, please don't hesitate to reach out.</p>
    </td>
@endsection
