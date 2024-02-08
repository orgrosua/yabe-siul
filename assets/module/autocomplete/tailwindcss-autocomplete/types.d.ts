declare module "tailwindcss/src/lib/expandApplyAtRules.js" {
  export default function expandApplyAtRules(): void;
}

declare module "tailwindcss/src/util/splitAtTopLevelOnly.js" {
  export function splitAtTopLevelOnly(
    input: string,
    separator: string
  ): string[];
}

declare module "tailwindcss/src/lib/generateRules.js" {
  export function generateRules(): void;
}

declare module "tailwindcss/src/lib/setupContextUtils.js" {
  import { Config } from "tailwindcss";
  import { Variant } from "tailwindcss-language-service";

  interface ChangedContent {
    content: string;
    extension?: string;
  }

  export interface JitContext {
    changedContent: ChangedContent[];
    getClassList: () => string[];
    getVariants: () => Variant[] | undefined;
    tailwindConfig: Config;
  }

  export function createContext(
    config: Config,
    changedContent?: ChangedContent[]
  ): JitContext;
}

declare module "tailwindcss/src/processTailwindFeatures.js" {
  import { AtRule, Plugin, Result, Root } from "postcss";
  import {
    createContext,
    JitContext,
  } from "tailwindcss/src/lib/setupContextUtils.js";

  type SetupContext = (root: Root, result: Result) => JitContext;

  interface ProcessTailwindFeaturesCallbackOptions {
    applyDirectives: Set<AtRule>;
    createContext: typeof createContext;
    registerDependency: () => unknown;
    tailwindDirectives: Set<string>;
  }

  export default function processTailwindFeatures(
    callback: (options: ProcessTailwindFeaturesCallbackOptions) => SetupContext
  ): Plugin;
}

declare module "tailwindcss/src/public/resolve-config.js" {
  import { Config } from "tailwindcss";

  export default function resolveConfig(
    tailwindConfig: Omit<Config, "content">
  ): Config;
}
