function getBasePath() {
    const path = window.location.pathname;
    const lastSlashIndex = path.lastIndexOf('/');
    
    if (lastSlashIndex <= 0) {
        return '';
    }
    
    let basePath = path.substring(0, lastSlashIndex + 1);
    
    if (!basePath.startsWith('/')) {
        basePath = '/' + basePath;
    }
    
    return basePath;
}