import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const EMPTY_EDITOR_VALUES = ['', '<p><br></p>', '<p></p>'];

export const isEmptyEditorContent = (html) => {
    if (!html) {
        return true;
    }

    const normalized = html.replace(/\s/g, '').toLowerCase();

    return EMPTY_EDITOR_VALUES.some((value) => normalized === value.replace(/\s/g, ''));
};

export const initRichTextEditor = ({
    container,
    textarea = null,
    placeholder = 'Enter details...',
    initialValue = '',
    toolbar = [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link'],
        ['clean'],
    ],
}) => {
    if (!container) {
        return null;
    }

    const quill = new Quill(container, {
        theme: 'snow',
        placeholder,
        modules: { toolbar },
    });

    const value = initialValue || textarea?.value || '';

    if (value && !isEmptyEditorContent(value)) {
        quill.root.innerHTML = value;
    }

    const sync = () => {
        if (!textarea) {
            return quill.root.innerHTML;
        }

        const html = quill.root.innerHTML;
        textarea.value = isEmptyEditorContent(html) ? '' : html;

        return textarea.value;
    };

    quill.on('text-change', sync);

    return { quill, sync };
};
