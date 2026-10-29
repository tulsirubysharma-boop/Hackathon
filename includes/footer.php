
</main>
<footer class="site-footer">
    <p>HealthSphere — thoughtful health support for every stage of life.</p>
    <p class="footer-note">Health information on this platform is for general support and does not replace professional medical advice.</p>
</footer>
<script>
document.querySelectorAll('input[type="password"]').forEach(input => {
    if (input.dataset.visibilityToggle === 'ready') return;
    const wrapper = document.createElement('span');
    wrapper.className = 'password-input-wrap';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const toggle = document.createElement('button');
    toggle.className = 'password-visibility-toggle';
    toggle.type = 'button';
    toggle.textContent = 'Show';
    toggle.setAttribute('aria-label', 'Show password');
    toggle.setAttribute('aria-pressed', 'false');
    toggle.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        toggle.textContent = reveal ? 'Hide' : 'Show';
        toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        toggle.setAttribute('aria-pressed', String(reveal));
    });
    wrapper.appendChild(toggle);
    input.dataset.visibilityToggle = 'ready';
});
</script>
</body>
</html>
