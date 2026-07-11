import './bootstrap';

document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
    await navigator.clipboard.writeText(button.dataset.copy); const old = button.textContent; button.textContent = '已复制'; setTimeout(() => button.textContent = old, 1500);
}));

const moveButton = document.querySelector('#moveButton');
if (moveButton) moveButton.addEventListener('click', async () => {
    if (moveButton.disabled) return; moveButton.disabled = true;
    const event = document.querySelector('#event'); const dice = document.querySelector('#dice'); event.textContent = '骰子滚动中…'; dice.textContent = '🎲';
    try {
        const response = await fetch(moveButton.dataset.url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({request_id:crypto.randomUUID()})});
        const data = await response.json(); if (!response.ok) throw new Error(data.message || '操作失败');
        if (data.dice_value) dice.textContent = ['⚀','⚁','⚂','⚃','⚄','⚅'][data.dice_value-1];
        event.textContent = data.result_text; document.querySelector('#chance').textContent = Math.max(0, Number(document.querySelector('#chance').textContent)-1); document.querySelector('#lap').textContent = data.to_lap; document.querySelector('#position').textContent = data.to_position;
        document.querySelectorAll('.cell').forEach(c=>{c.classList.remove('active');c.querySelector('.piece')?.remove()}); const cell=document.querySelector(`[data-position="${data.to_position}"]`); cell?.classList.add('active'); if(cell) cell.insertAdjacentHTML('beforeend','<em class="piece">🚗</em>');
        setTimeout(()=>location.reload(),2200);
    } catch (error) { event.textContent = error.message; moveButton.disabled = false; }
});
