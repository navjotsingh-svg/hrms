@extends('layouts.app')



@section('title', 'HR Assistant - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

        <div>

            <h1 class="page-title mb-1">HR Assistant</h1>

            <p class="page-subtitle mb-0" id="assistantSubtitle">Ask questions about your own leave, attendance, profile, and payslips.</p>

        </div>

        <span class="badge text-bg-light border" id="assistantModeBadge">Loading…</span>

    </div>

@endsection



@section('content')

    <div id="assistantAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>



    <div class="content-card assistant-chat-card">

        <div class="assistant-chat-messages" id="assistantMessages" aria-live="polite">

            <div class="assistant-message assistant-message--assistant">

                <div class="assistant-message-bubble">

                    Hi! I can help with your leave balance, today's attendance, manager details, leave requests, holidays, and payslip availability.

                </div>

            </div>

        </div>



        <div class="assistant-suggestions border-top" id="assistantSuggestions"></div>



        <div class="assistant-chat-composer border-top">

            <form id="assistantChatForm" class="assistant-chat-form">

                <label for="assistantMessageInput" class="visually-hidden">Message</label>

                <textarea

                    id="assistantMessageInput"

                    class="form-control assistant-chat-input"

                    rows="2"

                    maxlength="1000"

                    placeholder="Ask about your leave, attendance, manager, holidays…"

                ></textarea>

                <button type="submit" class="btn btn-primary assistant-chat-send" id="assistantSendBtn">Send</button>

            </form>

            <div class="small text-muted mt-2">Answers are based only on your own HR data. For other employees or admin actions, contact HR.</div>

        </div>

    </div>



    @vite(['resources/js/employee-assistant.js'])

@endsection

