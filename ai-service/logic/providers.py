"""
Proveedores de IA centralizados.
Cambiar de Claude a OpenAI es cambiar 1 parámetro.
Usa clientes async para no bloquear el event loop de FastAPI.
"""
import anthropic
import openai


async def call_anthropic(api_key: str, model: str, system_prompt: str, user_prompt: str, max_tokens: int = 500, temperature: float = 0.7) -> str | None:
    """Llamar a Anthropic Claude (async)."""
    try:
        client = anthropic.AsyncAnthropic(api_key=api_key)
        message = await client.messages.create(
            model=model,
            max_tokens=max_tokens,
            temperature=temperature,
            system=system_prompt,
            messages=[{"role": "user", "content": user_prompt}],
        )
        return message.content[0].text.strip() if message.content else None
    except Exception as e:
        print(f"[Anthropic Error] {e}")
        return None


async def call_openai(api_key: str, model: str, system_prompt: str, user_prompt: str, max_tokens: int = 500, temperature: float = 0.7) -> str | None:
    """Llamar a OpenAI (async)."""
    try:
        client = openai.AsyncOpenAI(api_key=api_key)
        response = await client.chat.completions.create(
            model=model,
            max_tokens=max_tokens,
            temperature=temperature,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
        )
        return response.choices[0].message.content.strip() if response.choices else None
    except Exception as e:
        print(f"[OpenAI Error] {e}")
        return None


async def call_provider(provider: str, api_key: str, model: str, system_prompt: str, user_prompt: str, **kwargs) -> str | None:
    """Router: llama al proveedor correcto."""
    if provider == "anthropic":
        return await call_anthropic(api_key, model, system_prompt, user_prompt, **kwargs)
    elif provider == "openai":
        return await call_openai(api_key, model, system_prompt, user_prompt, **kwargs)
    else:
        raise ValueError(f"Proveedor no soportado: {provider}")
