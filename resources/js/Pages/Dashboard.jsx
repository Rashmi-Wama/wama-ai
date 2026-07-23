import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ModuleSidebar from '@/Components/ModuleSidebar';
import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const STARTER_PROMPTS = [
    'Show outstanding invoices above ₹50,000.',
    'Generate invoice for MKU project.',
    'Show sales between 1 May and 10 May.',
    'Which clients have overdue payment?',
    'Summarize pending payments.',
    'Generate monthly revenue summary.',
    'Show highest paying clients.',
    'Create invoice draft for Vistaar Finance.',
    'Read project notes and summarize payment risks.',
    'Which projects are delayed and unpaid?',
    'Share the pending payment summary on WhatsApp.',
    'Download the invoice for Apex Retail as PDF.',
];

const MAX_RETRIES = 2;

function TypingDots() {
    return (
        <div className="flex items-center gap-1.5 px-1 py-1">
            {[0, 1, 2].map((i) => (
                <span
                    key={i}
                    className="h-2 w-2 rounded-full bg-sage animate-bounce-dot"
                    style={{ animationDelay: `${i * 0.15}s` }}
                />
            ))}
        </div>
    );
}

function LinkifiedText({ content }) {
    const parts = String(content ?? '').split(/(https?:\/\/[^\s]+|\/invoices\/\d+\/pdf(?:\?[^\s]*)?)/g);

    return parts.map((part, index) => {
        const isUrl = /^https?:\/\//i.test(part);
        const isInvoicePdf = /^\/invoices\/\d+\/pdf(?:\?|$)/i.test(part)
            || /\/invoices\/\d+\/pdf(?:\?|$)/i.test(part);
        const isWhatsapp = /^https?:\/\/(?:www\.)?wa\.me\//i.test(part);

        if (!isUrl && !isInvoicePdf) {
            return part;
        }

        return (
            <a
                key={`${part}-${index}`}
                href={part}
                className={`mt-2 inline-flex items-center gap-2 rounded-xl px-3 py-2 font-semibold text-white shadow-sm transition ${
                    isWhatsapp
                        ? 'bg-[#25D366] hover:bg-[#1fb85a]'
                        : 'bg-leaf hover:bg-leaf/90'
                }`}
                target={isInvoicePdf ? undefined : '_blank'}
                rel={isInvoicePdf ? undefined : 'noreferrer'}
            >
                {isWhatsapp && (
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M20 11.5a8 8 0 0 1-11.9 7L3 20l1.5-5.1A8 8 0 1 1 20 11.5Z" strokeLinecap="round" strokeLinejoin="round" />
                        <path d="M8.5 9.5c.5 2.5 2 4 4.5 5" strokeLinecap="round" />
                    </svg>
                )}
                {isInvoicePdf ? 'Download PDF' : (isWhatsapp ? 'Share on WhatsApp' : part)}
            </a>
        );
    });
}

function tableCells(line) {
    const escapedPipe = '\u0000';

    return line
        .trim()
        .replace(/^\||\|$/g, '')
        .replace(/\\\|/g, escapedPipe)
        .split('|')
        .map((cell) => cell.trim().replaceAll(escapedPipe, '|'));
}

function MessageContent({ content, isAssistant }) {
    if (!isAssistant) {
        return content;
    }

    const lines = String(content ?? '').split('\n');
    const blocks = [];
    let textLines = [];

    const flushText = () => {
        if (textLines.length === 0) {
            return;
        }

        const text = textLines.join('\n');
        blocks.push(
            <div key={`text-${blocks.length}`} className="whitespace-pre-wrap">
                <LinkifiedText content={text} />
            </div>
        );
        textLines = [];
    };

    for (let index = 0; index < lines.length;) {
        const isHeader = lines[index]?.trim().startsWith('|');
        const isSeparator = /^\s*\|(?:\s*:?-{3,}:?\s*\|)+\s*$/.test(lines[index + 1] ?? '');

        if (!isHeader || !isSeparator) {
            textLines.push(lines[index]);
            index += 1;
            continue;
        }

        flushText();
        const headers = tableCells(lines[index]);
        index += 2;
        const rows = [];

        while (index < lines.length && lines[index].trim().startsWith('|')) {
            rows.push(tableCells(lines[index]));
            index += 1;
        }

        blocks.push(
            <div key={`table-${blocks.length}`} className="my-3 max-w-full overflow-x-auto rounded-2xl border border-sage/20 bg-white/70 whitespace-normal">
                <table className="min-w-full border-collapse text-left text-xs sm:text-sm">
                    <thead className="bg-leaf/10 text-bark">
                        <tr>
                            {headers.map((header, cellIndex) => (
                                <th key={`${header}-${cellIndex}`} className="whitespace-nowrap border-b border-sage/20 px-4 py-3 font-bold">
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-sage/15">
                        {rows.map((row, rowIndex) => (
                            <tr key={`row-${rowIndex}`} className="transition hover:bg-sage/5">
                                {headers.map((_, cellIndex) => (
                                    <td key={`cell-${cellIndex}`} className="whitespace-nowrap px-4 py-3 text-bark/75">
                                        <LinkifiedText content={row[cellIndex] ?? ''} />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    flushText();

    return <div className="space-y-2">{blocks}</div>;
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function Dashboard({ conversations: initialConversations = [], activeConversation = null }) {
    const [conversations, setConversations] = useState(initialConversations);
    const [activeId, setActiveId] = useState(activeConversation?.id ?? null);
    const [messages, setMessages] = useState(activeConversation?.messages ?? []);
    const [input, setInput] = useState('');
    const [isTyping, setIsTyping] = useState(false);
    const [isListening, setIsListening] = useState(false);
    const [loadingConversation, setLoadingConversation] = useState(false);
    const [lastFailedPrompt, setLastFailedPrompt] = useState(null);
    const endRef = useRef(null);
    const textareaRef = useRef(null);
    const recognitionRef = useRef(null);

    useEffect(() => {
        setConversations(initialConversations);
    }, [initialConversations]);

    useEffect(() => {
        setActiveId(activeConversation?.id ?? null);
        setMessages(activeConversation?.messages ?? []);
    }, [activeConversation]);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isTyping]);

    useEffect(() => () => {
        recognitionRef.current?.stop?.();
    }, []);

    const activeTitle = useMemo(() => {
        const found = conversations.find((c) => c.id === activeId);
        return found?.title ?? 'New chat';
    }, [conversations, activeId]);

    const voiceSupported = useMemo(() => {
        if (typeof window === 'undefined') {
            return false;
        }
        return Boolean(window.SpeechRecognition || window.webkitSpeechRecognition);
    }, []);

    const upsertConversation = (conversation) => {
        setConversations((prev) => {
            const others = prev.filter((c) => c.id !== conversation.id);
            return [conversation, ...others];
        });
    };

    const startNewChat = async () => {
        try {
            const { data } = await window.axios.post(route('ai-conversations.store'));
            const conversation = data.conversation;
            upsertConversation(conversation);
            setActiveId(conversation.id);
            setMessages([]);
            setInput('');
            setIsTyping(false);
            setLastFailedPrompt(null);
            router.get(route('dashboard'), { conversation: conversation.id }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: [],
            });
            textareaRef.current?.focus();
        } catch {
            setActiveId(null);
            setMessages([]);
            setInput('');
        }
    };

    const selectConversation = async (id) => {
        if (id === activeId || loadingConversation || isTyping) {
            return;
        }

        setLoadingConversation(true);
        setActiveId(id);

        try {
            const { data } = await window.axios.get(route('ai-conversations.show', id));
            setMessages(data.conversation.messages ?? []);
            upsertConversation({
                id: data.conversation.id,
                title: data.conversation.title,
            });
            router.get(route('dashboard'), { conversation: id }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: [],
            });
        } catch {
            // keep current state if load fails
        } finally {
            setLoadingConversation(false);
        }
    };

    const deleteConversation = async (id, e) => {
        e.stopPropagation();
        if (!confirm('Delete this conversation?')) {
            return;
        }

        try {
            await window.axios.delete(route('ai-conversations.destroy', id));
            const remaining = conversations.filter((c) => c.id !== id);
            setConversations(remaining);

            if (activeId === id) {
                if (remaining.length > 0) {
                    await selectConversation(remaining[0].id);
                } else {
                    setActiveId(null);
                    setMessages([]);
                    router.get(route('dashboard'), {}, {
                        preserveState: true,
                        replace: true,
                        only: [],
                    });
                }
            }
        } catch {
            // ignore
        }
    };

    const streamChat = async (text, conversationId) => {
        const response = await fetch(route('ai-chat.stream'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                message: text,
                conversation_id: conversationId,
            }),
        });

        if (!response.ok || !response.body) {
            throw new Error(`Stream failed (${response.status})`);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let assistantTempId = `temp-assistant-${Date.now()}`;
        let streamedReply = '';
        let conversationMeta = null;
        let finalMessages = null;

        setMessages((prev) => [
            ...prev,
            { id: assistantTempId, role: 'assistant', content: '' },
        ]);

        while (true) {
            const { done, value } = await reader.read();
            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });
            const parts = buffer.split('\n\n');
            buffer = parts.pop() ?? '';

            for (const part of parts) {
                const line = part
                    .split('\n')
                    .map((l) => l.trim())
                    .find((l) => l.startsWith('data:'));

                if (!line) {
                    continue;
                }

                const payload = JSON.parse(line.slice(5).trim());

                if (payload.type === 'meta') {
                    conversationMeta = payload.conversation;
                    upsertConversation(conversationMeta);
                    setActiveId(conversationMeta.id);
                }

                if (payload.type === 'delta') {
                    streamedReply += payload.content ?? '';
                    setMessages((prev) => prev.map((m) => (
                        m.id === assistantTempId
                            ? { ...m, content: streamedReply }
                            : m
                    )));
                }

                if (payload.type === 'done') {
                    finalMessages = payload.messages;
                    if (payload.reply && !streamedReply) {
                        streamedReply = payload.reply;
                    }
                }
            }
        }

        return { conversationMeta, finalMessages, streamedReply, assistantTempId };
    };

    const sendMessage = async (rawText, options = {}) => {
        const text = rawText.trim();
        const attempt = options.attempt ?? 0;
        const skipUserAppend = options.skipUserAppend ?? false;

        if (!text || isTyping) {
            return;
        }

        const tempUserId = `temp-user-${Date.now()}`;

        if (!skipUserAppend) {
            setMessages((prev) => [
                ...prev,
                { id: tempUserId, role: 'user', content: text },
            ]);
            setInput('');
            setLastFailedPrompt(null);
        }

        setIsTyping(true);

        try {
            const { conversationMeta, finalMessages, assistantTempId } = await streamChat(text, activeId);

            if (conversationMeta) {
                router.get(route('dashboard'), { conversation: conversationMeta.id }, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: [],
                });
            }

            setMessages((prev) => {
                let next = prev.filter((m) => m.id !== tempUserId && m.id !== assistantTempId);
                if (finalMessages?.length) {
                    next = [
                        ...next.filter((m) => !finalMessages.some((fm) => fm.id === m.id)),
                        ...finalMessages.filter((m) => m.role === 'user' || m.role === 'assistant'),
                    ];
                }
                return next;
            });
        } catch (error) {
            if (attempt < MAX_RETRIES) {
                setIsTyping(false);
                await new Promise((resolve) => setTimeout(resolve, 500 * (attempt + 1)));
                return sendMessage(text, { attempt: attempt + 1, skipUserAppend: true });
            }

            setLastFailedPrompt(text);
            const fallback =
                error?.response?.data?.message
                || error?.message
                || 'I could not process that request right now. Please retry.';

            setMessages((prev) => [
                ...prev.filter((m) => m.role !== 'assistant' || m.content !== ''),
                {
                    id: `temp-error-${Date.now()}`,
                    role: 'assistant',
                    content: `${fallback}\n\nTap Retry to send again.`,
                },
            ]);
        } finally {
            setIsTyping(false);
        }
    };

    const retryLast = () => {
        if (!lastFailedPrompt || isTyping) {
            return;
        }
        sendMessage(lastFailedPrompt);
    };

    const toggleVoice = () => {
        if (!voiceSupported || isTyping) {
            return;
        }

        if (isListening) {
            recognitionRef.current?.stop?.();
            setIsListening(false);
            return;
        }

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        recognition.lang = 'en-IN';
        recognition.interimResults = true;
        recognition.continuous = false;
        recognitionRef.current = recognition;

        recognition.onresult = (event) => {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; i += 1) {
                transcript += event.results[i][0].transcript;
            }
            setInput((prev) => (prev ? `${prev} ${transcript}`.trim() : transcript.trim()));
        };

        recognition.onerror = () => setIsListening(false);
        recognition.onend = () => setIsListening(false);

        setIsListening(true);
        recognition.start();
    };

    const onSubmit = (e) => {
        e.preventDefault();
        sendMessage(input);
    };

    const onKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(input);
        }
    };

    return (
        <AuthenticatedLayout
            sidebar={
                <ModuleSidebar>
                    <div className="flex h-full flex-col gap-4">
                        <button
                            type="button"
                            onClick={startNewChat}
                            className="inline-flex items-center justify-center gap-2 rounded-2xl bg-leaf px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition hover:-translate-y-0.5 hover:bg-clay"
                        >
                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M12 5v14M5 12h14" strokeLinecap="round" />
                            </svg>
                            New chat
                        </button>

                        <div>
                            <p className="mb-2 px-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-clay">
                                Conversations
                            </p>
                            <div className="space-y-1.5">
                                {conversations.length === 0 && (
                                    <p className="px-2 py-2 text-xs text-clay">No chats yet.</p>
                                )}
                                {conversations.map((conversation) => (
                                    <div
                                        key={conversation.id}
                                        className={`group flex items-center gap-1 rounded-2xl transition ${
                                            conversation.id === activeId
                                                ? 'bg-sage text-white shadow-sm'
                                                : 'text-clay hover:bg-sage/10 hover:text-bark'
                                        }`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => selectConversation(conversation.id)}
                                            className="min-w-0 flex-1 rounded-2xl px-3 py-2.5 text-left text-sm"
                                        >
                                            <span className="line-clamp-1 font-medium">
                                                {conversation.title}
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={(e) => deleteConversation(conversation.id, e)}
                                            className={`mr-2 rounded-lg p-1.5 opacity-0 transition group-hover:opacity-100 ${
                                                conversation.id === activeId
                                                    ? 'hover:bg-white/20'
                                                    : 'hover:bg-sage/20'
                                            }`}
                                            aria-label="Delete conversation"
                                            title="Delete"
                                        >
                                            <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                <path d="M6 7h12M10 11v6M14 11v6M9 7V5h6v2M8 7l1 12h6l1-12" strokeLinecap="round" strokeLinejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </ModuleSidebar>
            }
        >
            <Head title={activeTitle || 'AI Chat'} />

            <div className="relative flex h-full min-h-0 flex-col">
                <div className="chat-scroll flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-10">
                    {loadingConversation ? (
                        <div className="flex h-full items-center justify-center text-sm text-clay">
                            Loading conversation…
                        </div>
                    ) : messages.length === 0 && !isTyping ? (
                        <div className="mx-auto flex h-full max-w-3xl flex-col items-center justify-center text-center animate-fade-up">
                            <div className="relative mb-6">
                                <div className="absolute inset-0 animate-pulse-soft rounded-full bg-sage/30 blur-2xl" />
                                <div className="relative flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-leaf to-sage shadow-glow animate-float">
                                    <svg className="h-10 w-10 text-cream" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7">
                                        <path d="M12 3v3M12 18v3M3 12h3M18 12h3M6.2 6.2l2.1 2.1M15.7 15.7l2.1 2.1M6.2 17.8l2.1-2.1M15.7 8.3l2.1-2.1" strokeLinecap="round" />
                                        <circle cx="12" cy="12" r="3.5" />
                                    </svg>
                                </div>
                            </div>

                            <h1 className="font-display text-3xl font-semibold text-bark sm:text-4xl">
                                How can I help you today?
                            </h1>
                            <p className="mt-3 max-w-xl text-sm leading-relaxed text-clay sm:text-base">
                                Ask about invoices, sales, overdue clients, drafts, PDF, email, or WhatsApp summaries.
                            </p>

                            <div className="mt-8 grid w-full gap-3 sm:grid-cols-2">
                                {STARTER_PROMPTS.map((prompt) => (
                                    <button
                                        key={prompt}
                                        type="button"
                                        onClick={() => sendMessage(prompt)}
                                        className="rounded-2xl border border-sage/25 bg-cream/80 px-4 py-4 text-left text-sm text-ink shadow-sm transition hover:-translate-y-0.5 hover:border-sage hover:bg-sage/10"
                                    >
                                        {prompt}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="mx-auto flex max-w-3xl flex-col gap-5 pb-4">
                            {messages.map((message) => (
                                <div
                                    key={message.id}
                                    className={`flex gap-3 animate-fade-up ${
                                        message.role === 'user' ? 'justify-end' : 'justify-start'
                                    }`}
                                >
                                    {message.role === 'assistant' && (
                                        <div className="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-leaf text-white shadow-sm">
                                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                <circle cx="12" cy="12" r="3" />
                                                <path d="M12 3v2M12 19v2M3 12h2M19 12h2" strokeLinecap="round" />
                                            </svg>
                                        </div>
                                    )}

                                    <div
                                        className={`whitespace-pre-wrap rounded-3xl px-4 py-3 text-sm leading-relaxed sm:text-[15px] ${
                                            message.role === 'user'
                                                ? 'max-w-[85%] bg-leaf text-white shadow-glow'
                                                : 'max-w-[calc(100%-3rem)] border border-sage/20 bg-white/60 text-ink shadow-soft'
                                        }`}
                                    >
                                        <MessageContent
                                            content={message.content || (isTyping ? '' : '…')}
                                            isAssistant={message.role === 'assistant'}
                                        />
                                    </div>
                                </div>
                            ))}

                            {isTyping && messages[messages.length - 1]?.role !== 'assistant' && (
                                <div className="flex gap-3">
                                    <div className="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-leaf text-white">
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </div>
                                    <div className="rounded-3xl border border-sage/20 bg-white/60 px-4 py-3 shadow-soft">
                                        <TypingDots />
                                    </div>
                                </div>
                            )}

                            {lastFailedPrompt && !isTyping && (
                                <div className="flex justify-center">
                                    <button
                                        type="button"
                                        onClick={retryLast}
                                        className="rounded-2xl bg-leaf px-4 py-2 text-sm font-semibold text-white shadow-glow transition hover:bg-clay"
                                    >
                                        Retry last request
                                    </button>
                                </div>
                            )}
                            <div ref={endRef} />
                        </div>
                    )}
                </div>

                <div className="border-t border-sage/20 bg-cream/80 px-4 py-4 backdrop-blur-xl sm:px-6 lg:px-10">
                    <form onSubmit={onSubmit} className="mx-auto max-w-3xl">
                        <div className="flex items-end gap-2 rounded-[28px] border border-sage/25 bg-cream p-2 shadow-soft transition focus-within:border-sage focus-within:shadow-glow">
                            {/* {voiceSupported && (
                                <button
                                    type="button"
                                    onClick={toggleVoice}
                                    disabled={isTyping}
                                    className={`mb-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl transition ${
                                        isListening
                                            ? 'bg-red-500 text-white'
                                            : 'bg-sage/15 text-leaf hover:bg-sage hover:text-white'
                                    } disabled:opacity-40`}
                                    aria-label={isListening ? 'Stop voice input' : 'Start voice input'}
                                    title={isListening ? 'Listening…' : 'Voice input'}
                                >
                                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <path d="M12 3a3 3 0 0 1 3 3v6a3 3 0 1 1-6 0V6a3 3 0 0 1 3-3Z" />
                                        <path d="M19 11a7 7 0 0 1-14 0M12 18v3" strokeLinecap="round" />
                                    </svg>
                                </button>
                            )} */}
                            <textarea
                                ref={textareaRef}
                                rows={1}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={onKeyDown}
                                placeholder="Message Wama AI…"
                                className="max-h-40 min-h-[48px] flex-1 resize-none border-0 bg-transparent px-3 py-3 text-sm text-ink placeholder:text-clay/80 focus:outline-none focus:ring-0"
                            />
                            <button
                                type="submit"
                                disabled={!input.trim() || isTyping}
                                className="mb-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-leaf text-white transition hover:bg-clay disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="Send message"
                            >
                                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M3.4 20.6 21 12 3.4 3.4l.1 6.9L15 12l-11.5 1.7.02 6.9Z" />
                                </svg>
                            </button>
                        </div>
                        
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
