const escapeHtml = (text) =>
    text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const inline = (text) =>
    text
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');

export const renderMarkdown = (md) => {
    const lines = escapeHtml(md.trim()).split('\n');
    const out = [];
    let list = null;

    const closeList = () => {
        if (list !== null) {
            out.push(`</${list}>`);
            list = null;
        }
    };

    for (const rawLine of lines) {
        const line = rawLine.replace(/\s+$/, '');
        if (!line.trim()) {
            closeList();
            out.push('');
            continue;
        }

        const heading = line.match(/^(#{1,3})\s+(.*)/);
        if (heading !== null) {
            closeList();
            out.push(`<h4>${inline(heading[2])}</h4>`);
            continue;
        }

        const ordered = line.match(/^\d+\.\s+(.*)/);
        const unordered = line.match(/^[-*]\s+(.*)/);
        const tag = ordered !== null ? 'ol' : unordered !== null ? 'ul' : null;
        if (tag !== null) {
            if (list !== tag) {
                closeList();
                out.push(`<${tag}>`);
                list = tag;
            }
            out.push(`<li>${inline(ordered !== null ? ordered[1] : unordered[1])}</li>`);
            continue;
        }

        closeList();
        out.push(`<p>${inline(line)}</p>`);
    }
    closeList();

    return out.join('\n');
};
