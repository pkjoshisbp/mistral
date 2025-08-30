import os
from functools import lru_cache
from threading import Lock
try:
    from llama_cpp import Llama  # type: ignore
except ImportError:  # Allow service to start even if llama-cpp not installed in venv yet
    Llama = None  # fallback sentinel

# Minimal, lazily-loaded rewrite model utility.
# Environment variables allow tuning without code edits.
MODEL_PATH = os.getenv("REWRITE_MODEL_PATH", "models/llama-3.2-1b-instruct/llama-3.2-1b-instruct.Q4_K_M.gguf")
N_CTX = int(os.getenv("REWRITE_N_CTX", "2048"))
N_THREADS = int(os.getenv("REWRITE_N_THREADS", str(os.cpu_count() or 4)))
TEMPERATURE = float(os.getenv("REWRITE_TEMPERATURE", "0.2"))
TOP_P = float(os.getenv("REWRITE_TOP_P", "0.9"))
MAX_TOKENS = int(os.getenv("REWRITE_MAX_TOKENS", "128"))
SYSTEM_PROMPT = os.getenv(
    "REWRITE_SYSTEM_PROMPT",
    "You rewrite user inputs into clear, concise, unambiguous prompts for retrieval and QA. Output only the rewritten prompt."
)

_model_lock = Lock()
_llm_instance = None


def _resolve_model_path(path: str) -> str:
    # If relative, resolve relative to this file's directory parent (project root assumption)
    if not os.path.isabs(path):
        base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
        path = os.path.join(base_dir, path)
    return path


def _load_model():
    global _llm_instance
    if _llm_instance is None:
        with _model_lock:
            if _llm_instance is None:  # double-checked
                resolved = _resolve_model_path(MODEL_PATH)
                if not os.path.exists(resolved):
                    raise FileNotFoundError(f"Rewrite model file missing: {resolved}. Download a GGUF (e.g. Q4_K_M) and set REWRITE_MODEL_PATH if custom.")
                if Llama is None:
                    raise ImportError("llama-cpp-python not installed in this environment. Install it or remove /rewrite usage.")
                _llm_instance = Llama(
                    model_path=resolved,
                    n_ctx=N_CTX,
                    n_threads=N_THREADS,
                )
    return _llm_instance


def rewrite_prompt(user_text: str) -> str:
    llm = _load_model()
    messages = [
        {"role": "system", "content": SYSTEM_PROMPT},
        {"role": "user", "content": user_text.strip()},
    ]
    out = llm.create_chat_completion(
        messages=messages,
        max_tokens=MAX_TOKENS,
        temperature=TEMPERATURE,
        top_p=TOP_P,
    )
    return out["choices"][0]["message"]["content"].strip()


if __name__ == "__main__":
    import sys
    print(rewrite_prompt(sys.argv[1] if len(sys.argv) > 1 else "plz find invoices for last qtr"))
