// Theme toggle (Bootstrap 5.3 data-bs-theme)
(function(){
  function applyTheme(theme){
    const root = document.documentElement;
    if(theme === 'dark'){
      root.setAttribute('data-bs-theme', 'dark');
    }else if(theme === 'light'){
      root.setAttribute('data-bs-theme', 'light');
    }else{
      root.removeAttribute('data-bs-theme'); // auto
    }
  }

  function nextTheme(current){
    if(current === 'auto') return 'light';
    if(current === 'light') return 'dark';
    return 'auto';
  }

  document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('themeToggle');
    if(!btn) return;

    btn.addEventListener('click', function(){
      const current = localStorage.getItem('theme') || 'auto';
      const nxt = nextTheme(current);
      localStorage.setItem('theme', nxt);
      applyTheme(nxt);
    });
  });
})();
