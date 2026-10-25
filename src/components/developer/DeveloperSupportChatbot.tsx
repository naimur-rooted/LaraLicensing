import React, { useState, useRef, useEffect } from "react";
import {
  MessageSquare,
  X,
  Maximize2,
  Minimize2,
  Send,
  HelpCircle,
  Bug,
  Lightbulb,
  ArrowRight,
  Home,
  Bot,
  User,
  Sparkles,
  Loader2,
  ChevronLeft,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { getApiBaseUrl } from "@/lib/lara-api-client";
import { getLaraAccessToken } from "@/lib/lara-api-session";

interface ChatMessage {
  id: string;
  sender: "user" | "bot";
  text: string;
  timestamp: string;
}

interface DeveloperSupportChatbotProps {
  userName?: string;
}

export function DeveloperSupportChatbot({ userName = "Naimur" }: DeveloperSupportChatbotProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [isExpanded, setIsExpanded] = useState(false);
  const [activeTab, setActiveTab] = useState<"home" | "messages">("home");
  const [messages, setMessages] = useState<ChatMessage[]>([
    {
      id: "welcome-msg",
      sender: "bot",
      text: "👋 Hi there! I'm **LaraLicensing AI Copilot**, your built-in developer assistant. Ask me anything about Ed25519 node-locking, machine seat limits, offline JWT verification, or client SDK integration!",
      timestamp: "Just now",
    },
  ]);
  const [inputText, setInputText] = useState("");
  const [isThinking, setIsThinking] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  };

  useEffect(() => {
    if (activeTab === "messages") {
      scrollToBottom();
    }
  }, [messages, activeTab]);

  const handleSendMessage = async (textToSend?: string) => {
    const text = (textToSend || inputText).trim();
    if (!text || isThinking) return;

    const userMsg: ChatMessage = {
      id: `user-${Date.now()}`,
      sender: "user",
      text,
      timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
    };

    setMessages((prev) => [...prev, userMsg]);
    setInputText("");
    setActiveTab("messages");
    setIsThinking(true);

    try {
      const historyPayload = messages.slice(-6).map((m) => ({
        sender: m.sender,
        text: m.text,
      }));

      const token = getLaraAccessToken();
      const headers: Record<string, string> = { "Content-Type": "application/json" };
      if (token) headers["Authorization"] = `Bearer ${token}`;

      const res = await fetch(`${getApiBaseUrl()}/api/v1/developer/ai/chat`, {
        method: "POST",
        headers,
        body: JSON.stringify({
          message: text,
          history: historyPayload,
          context: `Developer User: ${userName}. Platform: LaraLicensing (Split-DB SQLite sharding, Ed25519 signatures, headless REST APIs).`,
        }),
      });

      const json = await res.json();
      const reply =
        json.IsSuccess && json.Data?.reply
          ? json.Data.reply
          : "I am having trouble connecting to Gemini right now. You can verify licenses using POST /api/v1/products/{slug}/verify!";

      const botMsg: ChatMessage = {
        id: `bot-${Date.now()}`,
        sender: "bot",
        text: reply,
        timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
      };

      setMessages((prev) => [...prev, botMsg]);
    } catch {
      const errorMsg: ChatMessage = {
        id: `bot-${Date.now()}`,
        sender: "bot",
        text: "Network error communicating with the AI Copilot. Please check your backend connection.",
        timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
      };
      setMessages((prev) => [...prev, errorMsg]);
    } finally {
      setIsThinking(false);
    }
  };

  const handleActionClick = (prompt: string) => {
    setActiveTab("messages");
    handleSendMessage(prompt);
  };

  return (
    <>
      {/* Floating Launcher Button matching Cryptlex */}
      {!isOpen && (
        <button
          type="button"
          onClick={() => setIsOpen(true)}
          className="fixed bottom-5 right-5 z-50 flex items-center justify-center size-14 rounded-full bg-zinc-950 dark:bg-zinc-900 text-white shadow-xl hover:scale-105 active:scale-95 transition-all duration-200 border border-zinc-800"
          aria-label="Open AI Developer Support"
          title="Chat with AI Copilot"
        >
          <div className="relative">
            <MessageSquare className="size-6 text-white" />
            <span className="absolute -top-1 -right-1 size-3 rounded-full bg-primary ring-2 ring-background animate-pulse" />
          </div>
        </button>
      )}

      {/* Floating Chat Modal Panel */}
      {isOpen && (
        <div
          className={`fixed z-50 transition-all duration-300 flex flex-col bg-background border border-border shadow-2xl overflow-hidden ${
            isExpanded
              ? "bottom-4 right-4 top-4 left-4 sm:left-auto sm:w-[560px] rounded-2xl"
              : "bottom-5 right-5 w-[90vw] sm:w-[380px] h-[550px] rounded-2xl"
          }`}
        >
          {/* Header Gradient matching Cryptlex screenshot */}
          <div className="relative bg-gradient-to-br from-blue-700 via-indigo-600 to-blue-500 text-white p-5 pb-6 select-none shrink-0 shadow-md">
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                <div className="flex items-center justify-center size-7 rounded-lg bg-white/20 backdrop-blur-xs text-white">
                  <Bot className="size-4" />
                </div>
                <span className="text-xs font-semibold tracking-wide uppercase text-blue-100 flex items-center gap-1">
                  <Sparkles className="size-3 text-amber-300" /> LaraLicensing AI
                </span>
              </div>

              <div className="flex items-center gap-1 text-white/80">
                <button
                  type="button"
                  onClick={() => setIsExpanded(!isExpanded)}
                  className="p-1 hover:text-white hover:bg-white/10 rounded transition-colors"
                  title={isExpanded ? "Collapse" : "Expand"}
                >
                  {isExpanded ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
                </button>
                <button
                  type="button"
                  onClick={() => setIsOpen(false)}
                  className="p-1 hover:text-white hover:bg-white/10 rounded transition-colors"
                  title="Close"
                >
                  <X className="size-4" />
                </button>
              </div>
            </div>

            {/* Back button when in messages tab */}
            {activeTab === "messages" && (
              <button
                type="button"
                onClick={() => setActiveTab("home")}
                className="flex items-center gap-1 text-xs text-white/90 hover:text-white mb-2 font-medium"
              >
                <ChevronLeft className="size-3.5" /> Back to menu
              </button>
            )}

            <h2 className="text-xl font-bold tracking-tight text-white">
              Hi {userName} <span className="inline-block animate-wave">👋</span>
            </h2>
            <p className="text-xs text-blue-100/90 mt-0.5">
              {activeTab === "home" ? "How can we help you today?" : "Ask any licensing or API question"}
            </p>
          </div>

          {/* Body Content */}
          <div className="flex-1 overflow-y-auto flex flex-col bg-muted/10">
            {activeTab === "home" ? (
              /* Home View with 3 Action Cards matching screenshot */
              <div className="p-4 space-y-3 animate-in fade-in duration-200 flex-1">
                <button
                  type="button"
                  onClick={() => handleActionClick("I have a question about license verification and API integration.")}
                  className="w-full flex items-center justify-between p-3.5 rounded-xl border bg-card hover:bg-muted/40 hover:border-primary/50 transition-all text-left shadow-2xs group"
                >
                  <div>
                    <div className="text-xs font-semibold text-foreground">Ask a question</div>
                    <div className="text-[11px] text-muted-foreground mt-0.5">We are here to help</div>
                  </div>
                  <div className="size-7 rounded-lg bg-blue-500/10 text-blue-600 flex items-center justify-center group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                    <MessageSquare className="size-3.5" />
                  </div>
                </button>

                <button
                  type="button"
                  onClick={() => handleActionClick("I found an issue with license quota or verification: ")}
                  className="w-full flex items-center justify-between p-3.5 rounded-xl border bg-card hover:bg-muted/40 hover:border-primary/50 transition-all text-left shadow-2xs group"
                >
                  <div>
                    <div className="text-xs font-semibold text-foreground">Report an issue</div>
                    <div className="text-[11px] text-muted-foreground mt-0.5">Found a bug? Let us know.</div>
                  </div>
                  <div className="size-7 rounded-lg bg-rose-500/10 text-rose-600 flex items-center justify-center group-hover:bg-rose-600 group-hover:text-white transition-colors">
                    <Bug className="size-3.5" />
                  </div>
                </button>

                <button
                  type="button"
                  onClick={() => handleActionClick("I would like to request a new feature: ")}
                  className="w-full flex items-center justify-between p-3.5 rounded-xl border bg-card hover:bg-muted/40 hover:border-primary/50 transition-all text-left shadow-2xs group"
                >
                  <div>
                    <div className="text-xs font-semibold text-foreground">Request a feature</div>
                    <div className="text-[11px] text-muted-foreground mt-0.5">What would you like to see next?</div>
                  </div>
                  <div className="size-7 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition-colors">
                    <Lightbulb className="size-3.5" />
                  </div>
                </button>

                {/* Popular Quick Prompts */}
                <div className="pt-2">
                  <p className="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider mb-2 px-1">
                    Suggested Questions
                  </p>
                  <div className="space-y-1.5">
                    {[
                      "How do offline Ed25519 tokens work?",
                      "How does Cryptlex compare to LaraLicensing?",
                      "How do I handle SeatLimitExceeded 403 errors?",
                      "How to integrate with Python or Chrome Extension?",
                    ].map((prompt, i) => (
                      <button
                        key={i}
                        type="button"
                        onClick={() => handleActionClick(prompt)}
                        className="w-full text-left text-xs text-muted-foreground hover:text-foreground hover:bg-muted/60 p-2 rounded-lg border border-dashed border-border transition-colors flex items-center justify-between"
                      >
                        <span className="truncate">{prompt}</span>
                        <ArrowRight className="size-3 shrink-0 text-primary" />
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            ) : (
              /* Active Chat Message Stream */
              <div className="flex-1 p-3.5 space-y-3 overflow-y-auto animate-in fade-in duration-200">
                {messages.map((m) => (
                  <div
                    key={m.id}
                    className={`flex gap-2 ${m.sender === "user" ? "justify-end" : "justify-start"}`}
                  >
                    {m.sender === "bot" && (
                      <div className="size-6 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                        <Bot className="size-3.5" />
                      </div>
                    )}
                    <div
                      className={`max-w-[85%] rounded-2xl px-3.5 py-2.5 text-xs leading-relaxed ${
                        m.sender === "user"
                          ? "bg-primary text-primary-foreground rounded-br-xs shadow-xs"
                          : "bg-card border text-foreground rounded-bl-xs shadow-2xs whitespace-pre-wrap"
                      }`}
                    >
                      {m.text}
                      <div
                        className={`text-[9px] mt-1 ${
                          m.sender === "user" ? "text-primary-foreground/70" : "text-muted-foreground"
                        }`}
                      >
                        {m.timestamp}
                      </div>
                    </div>
                  </div>
                ))}

                {isThinking && (
                  <div className="flex gap-2 items-center text-xs text-muted-foreground p-1">
                    <div className="size-6 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                      <Bot className="size-3.5" />
                    </div>
                    <div className="flex items-center gap-1.5 bg-card border rounded-2xl px-3 py-2 shadow-2xs">
                      <Loader2 className="size-3 animate-spin text-primary" />
                      <span>Copilot is writing...</span>
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>
            )}
          </div>

          {/* Chat Input Bar (visible when in messages tab or always ready) */}
          {activeTab === "messages" && (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleSendMessage();
              }}
              className="p-2.5 border-t bg-background flex items-center gap-2 shrink-0"
            >
              <Input
                placeholder="Ask about licenses, SDKs, or errors..."
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                disabled={isThinking}
                className="h-9 text-xs bg-muted/20"
                autoFocus
              />
              <Button
                type="submit"
                size="icon"
                disabled={!inputText.trim() || isThinking}
                className="size-9 bg-primary text-primary-foreground shrink-0 shadow-xs"
              >
                <Send className="size-3.5" />
              </Button>
            </form>
          )}

          {/* Bottom Tabs matching Cryptlex launcher (Home / Messages) */}
          <div className="border-t bg-card/60 backdrop-blur-xs py-1.5 px-3 flex items-center justify-around text-xs shrink-0 select-none">
            <button
              type="button"
              onClick={() => setActiveTab("home")}
              className={`flex flex-col items-center gap-0.5 py-1 px-4 rounded-md transition-colors ${
                activeTab === "home" ? "text-primary font-semibold" : "text-muted-foreground hover:text-foreground"
              }`}
            >
              <Home className="size-4" />
              <span className="text-[10px]">Home</span>
            </button>
            <button
              type="button"
              onClick={() => setActiveTab("messages")}
              className={`flex flex-col items-center gap-0.5 py-1 px-4 rounded-md transition-colors relative ${
                activeTab === "messages" ? "text-primary font-semibold" : "text-muted-foreground hover:text-foreground"
              }`}
            >
              <MessageSquare className="size-4" />
              <span className="text-[10px]">Messages</span>
              {messages.length > 1 && activeTab !== "messages" && (
                <span className="absolute top-1 right-3 size-1.5 rounded-full bg-primary" />
              )}
            </button>
          </div>
        </div>
      )}
    </>
  );
}
