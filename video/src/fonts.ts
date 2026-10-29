import { loadFont } from "@remotion/fonts";
import { staticFile } from "remotion";
import { FONTS } from "./theme";

export const loadFonts = (): Promise<unknown> =>
  Promise.all([
    loadFont({
      family: FONTS.display,
      url: staticFile("fonts/poppins-800.woff2"),
      weight: "800",
      style: "normal",
    }),
    loadFont({
      family: FONTS.motto,
      url: staticFile("fonts/poppins-600-italic.woff2"),
      weight: "600",
      style: "italic",
    }),
  ]);

loadFonts();
