import {byPrefixAndName} from '@awesome.me/kit-ed8e499057/icons';

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        $fa: typeof byPrefixAndName;
    }
}
